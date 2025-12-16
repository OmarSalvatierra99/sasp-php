<?php
$title = 'SASP - Sin datos';
ob_start();
?>

<div class="empty-state">
  <p><?php echo htmlspecialchars($mensaje ?? 'No hay datos disponibles'); ?></p>
  <a href="/dashboard" class="btn btn-primary">Volver al inicio</a>
</div>

<?php
$content = ob_get_clean();
include 'base.php';
?>
