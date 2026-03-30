<?php
/*
    Plantilla de email: recuperación de contraseña
    Variables esperadas:
        $cliente   — objeto Cliente (nombre, apellido, email)
        $reset_url — URL completa con token
        $negocio   — nombre del negocio
        $base_url  — URL base de la tienda
*/
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Recupera tu contraseña</title>
<style>
  body      { margin: 0; padding: 0; background: #f4f6f8; font-family: Arial, sans-serif; color: #333; }
  .wrapper  { max-width: 600px; margin: 0 auto; background: #ffffff; }
  .header   { background: #1a6b2f; padding: 30px 40px; text-align: center; }
  .header h1{ margin: 0; color: #fff; font-size: 22px; }
  .header p { margin: 6px 0 0; color: #a8d5b5; font-size: 14px; }
  .body     { padding: 30px 40px; }
  .body h2  { font-size: 18px; margin-bottom: 4px; }
  .body p   { font-size: 14px; line-height: 1.6; color: #444; }
  .btn-wrap { text-align: center; margin: 28px 0; }
  .btn      { display: inline-block; background: #e85d04; color: #fff; text-decoration: none;
              padding: 14px 32px; border-radius: 6px; font-size: 15px; font-weight: bold; }
  .note     { background: #f8faf8; border-left: 3px solid #1a6b2f; border-radius: 4px;
              padding: 12px 16px; font-size: 13px; color: #555; margin: 20px 0; }
  .url-text { word-break: break-all; color: #1a6b2f; font-size: 12px; }
  .footer   { background: #f0f0f0; padding: 20px 40px; text-align: center;
              font-size: 12px; color: #888; }
</style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>🔑 Recupera tu contraseña</h1>
        <p><?= htmlspecialchars($negocio) ?></p>
    </div>
    <div class="body">
        <h2>Hola, <?= htmlspecialchars($cliente->nombre) ?></h2>
        <p>Recibimos una solicitud para restablecer la contraseña de tu cuenta
           (<strong><?= htmlspecialchars($cliente->email) ?></strong>).</p>
        <p>Haz clic en el botón para crear una nueva contraseña. El enlace es válido por
           <strong>60 minutos</strong>.</p>
        <div class="btn-wrap">
            <a href="<?= htmlspecialchars($reset_url) ?>" class="btn">Restablecer contraseña</a>
        </div>
        <div class="note">
            Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
            <span class="url-text"><?= htmlspecialchars($reset_url) ?></span>
        </div>
        <p>Si no solicitaste este cambio, ignora este correo. Tu contraseña actual no se modificará.</p>
    </div>
    <div class="footer">
        &copy; <?= date('Y') ?> <?= htmlspecialchars($negocio) ?>
        &nbsp;·&nbsp;
        <a href="<?= htmlspecialchars($base_url) ?>tienda" style="color:#1a6b2f;">Ir a la tienda</a>
    </div>
</div>
</body>
</html>
