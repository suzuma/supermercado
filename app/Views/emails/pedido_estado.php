<?php
/*
    Plantilla de email: cambio de estado de un pedido
    Variables esperadas:
        $pedido    — objeto Pedido (id, estado, total, direccion_entrega, fecha_entrega)
        $cliente   — objeto Cliente (nombre, apellido)
        $simbolo   — símbolo de moneda
        $negocio   — nombre del negocio
        $base_url  — URL base de la tienda
*/

$etiquetas = [
    'confirmado' => ['color' => '#2d6a4f', 'icono' => '✔️',  'titulo' => '¡Pedido confirmado!',
                     'desc' => 'Tu pedido fue confirmado y pronto será preparado para su envío.'],
    'enviado'    => ['color' => '#1d6fa4', 'icono' => '🚚',  'titulo' => '¡Tu pedido está en camino!',
                     'desc' => 'Tu pedido salió para ser entregado en tu dirección.'],
    'entregado'  => ['color' => '#5a2d82', 'icono' => '🎉',  'titulo' => '¡Pedido entregado!',
                     'desc' => 'Tu pedido fue entregado. ¡Gracias por tu compra!'],
    'cancelado'  => ['color' => '#c0392b', 'icono' => '❌',  'titulo' => 'Pedido cancelado',
                     'desc' => 'Tu pedido fue cancelado. Si tienes dudas, contáctanos.'],
];

$info = $etiquetas[$pedido->estado] ?? [
    'color' => '#555', 'icono' => 'ℹ️', 'titulo' => 'Actualización de tu pedido',
    'desc' => 'El estado de tu pedido fue actualizado.'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $info['titulo'] ?> #<?= str_pad($pedido->id, 4, '0', STR_PAD_LEFT) ?></title>
<style>
  body      { margin: 0; padding: 0; background: #f4f6f8; font-family: Arial, sans-serif; color: #333; }
  .wrapper  { max-width: 600px; margin: 0 auto; background: #ffffff; }
  .header   { padding: 30px 40px; text-align: center; }
  .header h1{ margin: 0; font-size: 22px; }
  .header p { margin: 6px 0 0; font-size: 14px; opacity: .8; }
  .icon     { font-size: 52px; display: block; margin-bottom: 12px; }
  .body     { padding: 24px 40px 30px; }
  .body p   { font-size: 14px; line-height: 1.6; }
  .folio    { display: inline-block; border: 1px solid currentColor; font-weight: bold;
              padding: 6px 18px; border-radius: 6px; font-size: 16px;
              letter-spacing: 1px; margin-bottom: 20px; }
  .info-box { background: #f8f9fa; border-radius: 8px; padding: 14px 18px; font-size: 14px;
              margin-bottom: 20px; }
  .info-box strong { display: block; margin-bottom: 4px; font-size: 12px; color: #888;
                     text-transform: uppercase; }
  .btn      { display: inline-block; color: #fff; text-decoration: none;
              padding: 12px 28px; border-radius: 6px; font-size: 14px; font-weight: bold; }
  .footer   { background: #f4f6f8; padding: 20px 40px; text-align: center;
              font-size: 12px; color: #999; }
</style>
</head>
<body>
<div class="wrapper">

  <div class="header" style="background: <?= $info['color'] ?>; color: #fff;">
    <span class="icon"><?= $info['icono'] ?></span>
    <h1><?= $info['titulo'] ?></h1>
    <p><?= htmlspecialchars($negocio) ?></p>
  </div>

  <div class="body">
    <p>Hola, <strong><?= htmlspecialchars($cliente->nombre) ?></strong>.</p>
    <p><?= $info['desc'] ?></p>

    <div class="folio" style="color: <?= $info['color'] ?>; border-color: <?= $info['color'] ?>">
      Folio #<?= str_pad($pedido->id, 4, '0', STR_PAD_LEFT) ?>
    </div>

    <div class="info-box">
      <strong>Dirección de entrega</strong>
      <?= htmlspecialchars($pedido->direccion_entrega) ?>
    </div>

    <?php if ($pedido->estado === 'enviado' && $pedido->fecha_entrega): ?>
    <div class="info-box">
      <strong>Entrega estimada</strong>
      <?= date('d/m/Y H:i', strtotime($pedido->fecha_entrega)) ?> hrs
    </div>
    <?php endif; ?>

    <div class="info-box">
      <strong>Total del pedido</strong>
      <?= $simbolo ?> <?= number_format($pedido->total, 2) ?>
    </div>

    <p>
      <a class="btn" href="<?= $base_url ?>tienda/mis-pedidos"
         style="background: <?= $info['color'] ?>">Ver mis pedidos</a>
    </p>
  </div>

  <div class="footer">
    &copy; <?= date('Y') ?> <?= htmlspecialchars($negocio) ?> — Este es un correo automático, no responder.
  </div>

</div>
</body>
</html>