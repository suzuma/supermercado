<?php
/*
    Plantilla de email: confirmación de pedido recibido
    Variables esperadas:
        $pedido    — objeto Pedido con relación detalles.producto cargada
        $cliente   — objeto Cliente (nombre, apellido, email)
        $simbolo   — símbolo de moneda
        $negocio   — nombre del negocio
        $base_url  — URL base de la tienda
*/
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pedido recibido #<?= str_pad($pedido->id, 4, '0', STR_PAD_LEFT) ?></title>
<style>
  body      { margin: 0; padding: 0; background: #f4f6f8; font-family: Arial, sans-serif; color: #333; }
  .wrapper  { max-width: 600px; margin: 0 auto; background: #ffffff; }
  .header   { background: #2d6a4f; padding: 30px 40px; text-align: center; }
  .header h1{ margin: 0; color: #fff; font-size: 22px; }
  .header p { margin: 6px 0 0; color: #b7e4c7; font-size: 14px; }
  .body     { padding: 30px 40px; }
  .body h2  { font-size: 18px; margin-bottom: 4px; }
  .body p   { font-size: 14px; line-height: 1.6; }
  .folio    { display: inline-block; background: #eafaf1; border: 1px solid #2d6a4f;
              color: #2d6a4f; font-weight: bold; padding: 8px 20px; border-radius: 6px;
              font-size: 18px; letter-spacing: 1px; margin-bottom: 24px; }
  table     { width: 100%; border-collapse: collapse; font-size: 14px; margin-bottom: 24px; }
  th        { background: #f0f0f0; padding: 10px 12px; text-align: left; font-size: 12px;
              text-transform: uppercase; color: #666; }
  td        { padding: 10px 12px; border-bottom: 1px solid #f0f0f0; }
  .total-row td { font-weight: bold; font-size: 16px; border-bottom: none; padding-top: 14px; }
  .address  { background: #f8f9fa; border-radius: 8px; padding: 14px 18px; font-size: 14px;
              margin-bottom: 24px; }
  .address strong { display: block; margin-bottom: 4px; font-size: 13px; color: #888;
                    text-transform: uppercase; }
  .btn      { display: inline-block; background: #2d6a4f; color: #fff; text-decoration: none;
              padding: 12px 28px; border-radius: 6px; font-size: 14px; font-weight: bold; }
  .footer   { background: #f4f6f8; padding: 20px 40px; text-align: center;
              font-size: 12px; color: #999; }
</style>
</head>
<body>
<div class="wrapper">

  <div class="header">
    <h1>✅ ¡Pedido recibido!</h1>
    <p><?= htmlspecialchars($negocio) ?></p>
  </div>

  <div class="body">
    <h2>Hola, <?= htmlspecialchars($cliente->nombre) ?>.</h2>
    <p>Tu pedido ha sido recibido y está siendo procesado. Te notificaremos cuando sea confirmado.</p>

    <div class="folio">Folio #<?= str_pad($pedido->id, 4, '0', STR_PAD_LEFT) ?></div>

    <table>
      <thead>
        <tr>
          <th>Producto</th>
          <th style="text-align:right">Cant.</th>
          <th style="text-align:right">Subtotal</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pedido->detalles as $d): ?>
        <tr>
          <td><?= htmlspecialchars($d->producto->nombre ?? 'Producto') ?></td>
          <td style="text-align:right"><?= $d->cantidad ?></td>
          <td style="text-align:right"><?= $simbolo ?> <?= number_format($d->subtotal, 2) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="total-row">
          <td colspan="2">Total</td>
          <td style="text-align:right"><?= $simbolo ?> <?= number_format($pedido->total, 2) ?></td>
        </tr>
      </tbody>
    </table>

    <div class="address">
      <strong>Dirección de entrega</strong>
      <?= htmlspecialchars($pedido->direccion_entrega) ?>
    </div>

    <p>
      <a class="btn" href="<?= $base_url ?>tienda/mis-pedidos">Ver mis pedidos</a>
    </p>
  </div>

  <div class="footer">
    &copy; <?= date('Y') ?> <?= htmlspecialchars($negocio) ?> — Este es un correo automático, no responder.
  </div>

</div>
</body>
</html>