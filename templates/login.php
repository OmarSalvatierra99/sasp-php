<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SASP | Acceso</title>
  <link rel="icon" href="/img/ofs_logo.png" type="image/png">
  <link rel="stylesheet" href="/css/style.css">
</head>

<body class="login-body">
  <div class="login-wrapper">
    <div class="login-card">
      <div class="login-header">
        <img src="/img/ofs_logo.png" alt="OFS Logo" class="login-logo">
        <h1 class="login-title">SASP</h1>
        <p class="login-subtitle">
          Sistema de Auditoría de Servicios Personales<br>
          <span>Órgano de Fiscalización Superior del Estado de Tlaxcala</span>
        </p>
      </div>

      <?php if (isset($error)): ?>
      <div class="alert-error" role="alert"><?php echo htmlspecialchars((string)$error); ?></div>
      <?php endif; ?>

      <form method="POST" autocomplete="off" class="login-form">
        <div class="input-group">
          <input type="text" name="usuario" class="login-input" placeholder="Usuario" required autofocus autocomplete="username" aria-label="Usuario">
        </div>

        <div class="input-group">
          <input type="password" name="clave" class="login-input" placeholder="Clave de acceso" required autocomplete="current-password" aria-label="Clave de acceso">
        </div>

        <button type="submit" class="btn-login">Ingresar al sistema</button>
      </form>
    </div>
  </div>
</body>
</html>
