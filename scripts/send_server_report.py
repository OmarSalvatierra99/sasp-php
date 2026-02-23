#!/usr/bin/env python3
import json
import os
import smtplib
import sqlite3
from email.mime.text import MIMEText
from pathlib import Path
from typing import Any

SMTP_SERVER = os.getenv("SMTP_SERVER", "smtp.gmail.com")
SMTP_PORT = int(os.getenv("SMTP_PORT", "587"))
EMAIL_USER = os.getenv("EMAIL_USER", "omargabrielsalvatierragarcia@gmail.com")
EMAIL_PASS = os.getenv("EMAIL_PASS", "")
DESTINO = os.getenv("DESTINO", "ap_felipec@ofstlaxcala.gob.mx")

PROJECT_ROOT = Path(__file__).resolve().parents[1]
SCIL_DB = os.getenv("SCIL_DB", str(PROJECT_ROOT / "scil.db"))


def _qnas_keys(raw_qnas: Any) -> list[str]:
    if isinstance(raw_qnas, dict):
        return list(raw_qnas.keys())
    if isinstance(raw_qnas, str):
        try:
            decoded = json.loads(raw_qnas)
            if isinstance(decoded, dict):
                return list(decoded.keys())
        except Exception:
            return []
    return []


def obtener_incompatibilidades(db_path: str) -> list[dict[str, Any]]:
    conn = sqlite3.connect(db_path)
    conn.row_factory = sqlite3.Row
    try:
        rows = conn.execute(
            """
            SELECT rfc, ente, nombre, qnas
            FROM registros_laborales
            ORDER BY rfc, ente
            """
        ).fetchall()
    finally:
        conn.close()

    rfcs_map: dict[str, list[dict[str, Any]]] = {}
    for row in rows:
        rfc = str(row["rfc"] or "").strip()
        if not rfc:
            continue
        rfcs_map.setdefault(rfc, []).append(
            {
                "rfc": rfc,
                "ente": str(row["ente"] or "").strip(),
                "nombre": str(row["nombre"] or "").strip(),
                "qnas": _qnas_keys(row["qnas"]),
            }
        )

    cruces: list[dict[str, Any]] = []
    for rfc, regs in rfcs_map.items():
        if len(regs) < 2:
            continue

        qnas_por_ente: dict[str, list[str]] = {}
        for reg in regs:
            ente = reg["ente"]
            if ente:
                qnas_por_ente[ente] = reg["qnas"]

        entes = list(qnas_por_ente.keys())
        qnas_con_cruce: set[str] = set()
        entes_con_cruce: set[str] = set()

        for i in range(len(entes)):
            for j in range(i + 1, len(entes)):
                e1 = entes[i]
                e2 = entes[j]
                inter = set(qnas_por_ente.get(e1, [])) & set(qnas_por_ente.get(e2, []))
                if inter:
                    qnas_con_cruce.update(inter)
                    entes_con_cruce.add(e1)
                    entes_con_cruce.add(e2)

        if qnas_con_cruce:
            cruces.append(
                {
                    "rfc": rfc,
                    "nombre": regs[0]["nombre"] if regs else "",
                    "entes": sorted(entes_con_cruce),
                    "qnas_cruce": sorted(qnas_con_cruce),
                }
            )

    return cruces


def obtener_catalogo_entes(db_path: str) -> dict[str, dict[str, str]]:
    conn = sqlite3.connect(db_path)
    conn.row_factory = sqlite3.Row
    try:
        rows = conn.execute(
            """
            SELECT clave, nombre, siglas, 'ENTE' AS tipo FROM entes
            UNION ALL
            SELECT clave, nombre, siglas, 'MUNICIPIO' AS tipo FROM municipios
            """
        ).fetchall()
    finally:
        conn.close()

    catalogo: dict[str, dict[str, str]] = {}
    for row in rows:
        clave = str(row["clave"] or "").strip()
        nombre = str(row["nombre"] or "").strip()
        siglas = str(row["siglas"] or "").strip()
        tipo = str(row["tipo"] or "ENTE").strip().upper()
        if clave:
            nombre_upper = (nombre or clave).upper()
            siglas_upper = siglas.upper() if siglas else clave.upper()
            catalogo[clave] = {
                "display": f"{nombre_upper} ({siglas_upper})",
                "tipo": tipo,
            }

    return catalogo


def construir_reporte_incompatibilidades(db_path: str, limite_detalle: int | None = None) -> str:
    cruces = obtener_incompatibilidades(db_path)
    catalogo_entes = obtener_catalogo_entes(db_path)

    lines: list[str] = []
    lines.append("REPORTE DE INCOMPATIBILIDADES - SASP")
    lines.append("=" * 60)
    lines.append(f"Base de datos: {db_path}")
    lines.append(f"Total casos incompatibilidad (RFC): {len(cruces)}")
    lines.append("")

    if not cruces:
        lines.append("No se detectaron casos de incompatibilidad por cruce de QNAs.")
        return "\n".join(lines)

    cruces.sort(key=lambda x: (-len(x["qnas_cruce"]), -len(x["entes"]), x["rfc"]))

    total_con_municipios = 0
    total_solo_estatales = 0
    for caso in cruces:
        tipos = {
            catalogo_entes.get(clave, {}).get("tipo", "ENTE")
            for clave in caso["entes"]
        }
        if "MUNICIPIO" in tipos:
            total_con_municipios += 1
        else:
            total_solo_estatales += 1

    lines.append("Resumen de ambito:")
    lines.append(f"- Casos con al menos un municipio: {total_con_municipios}")
    lines.append(f"- Casos solo entre entes estatales: {total_solo_estatales}")
    lines.append("")

    total_detalle = len(cruces) if limite_detalle is None else min(limite_detalle, len(cruces))
    lines.append(f"Detalle (primeros {total_detalle} casos):")
    lines.append("-" * 60)

    for idx, caso in enumerate(cruces[:total_detalle], start=1):
        entes_legibles = [
            catalogo_entes.get(clave, {}).get("display", clave)
            for clave in caso["entes"]
        ]
        entes = ", ".join(entes_legibles) if entes_legibles else "Sin ente"
        qnas = ", ".join(caso["qnas_cruce"]) if caso["qnas_cruce"] else "Sin QNA"
        lines.append(f"{idx:02d}. RFC: {caso['rfc']}")
        lines.append(f"    Nombre: {caso['nombre'] or 'Sin nombre'}")
        lines.append(f"    Entes con cruce: {entes}")
        lines.append(f"    QNAs en cruce: {qnas}")

    return "\n".join(lines)


def enviar_reporte(mensaje: str) -> None:
    faltantes = []
    if not EMAIL_USER:
        faltantes.append("EMAIL_USER")
    if not EMAIL_PASS:
        faltantes.append("EMAIL_PASS")
    if not DESTINO:
        faltantes.append("DESTINO")

    if faltantes:
        raise RuntimeError(
            "Faltan variables de entorno: " + ", ".join(faltantes)
        )

    msg = MIMEText(mensaje, _charset="utf-8")
    msg["Subject"] = "Reporte de incompatibilidades - SASP"
    msg["From"] = EMAIL_USER
    msg["To"] = DESTINO

    with smtplib.SMTP(SMTP_SERVER, SMTP_PORT, timeout=20) as server:
        server.starttls()
        server.login(EMAIL_USER, EMAIL_PASS)
        server.send_message(msg)


if __name__ == "__main__":
    reporte = construir_reporte_incompatibilidades(SCIL_DB)
    enviar_reporte(reporte)
    print("EMAIL_SENT")
