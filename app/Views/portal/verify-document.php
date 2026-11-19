<?php
$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$basePath = $basePath ?? '';
$code = $code ?? '';
$document = $document ?? null;
$error = $error ?? null;
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Validação de documento | SGECE</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f1f5f9;color:#172033;margin:0;padding:32px 16px}
        main{max-width:680px;margin:6vh auto;background:#fff;border-radius:12px;padding:32px;box-shadow:0 12px 32px #0f172a18}
        h1{font-size:1.6rem;margin-top:0}.field{margin:24px 0}.field label{display:block;font-weight:700;margin-bottom:8px}
        input{box-sizing:border-box;width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:6px}
        button,.button{display:inline-block;padding:11px 16px;border:0;border-radius:6px;background:#1d4ed8;color:#fff;text-decoration:none;cursor:pointer}
        .alert{padding:14px;border-radius:6px;margin:20px 0}.success{background:#dcfce7;color:#166534}.danger{background:#fee2e2;color:#991b1b}
        .details{line-height:1.8}.actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:20px}
        a.back{color:#1d4ed8;display:inline-block;margin-top:24px}
    </style>
</head>
<body>
<main>
    <h1>Validação de documento escolar</h1>
    <p>Confirme a autenticidade de um certificado, declaração anual ou declaração de notas do SGECE.</p>

    <form method="get" action="<?= $escape($basePath) ?>/validar-documento">
        <div class="field">
            <label for="codigo">Código de validação</label>
            <input id="codigo" name="codigo" value="<?= $escape($code) ?>" maxlength="48"
                   autocomplete="off" required>
        </div>
        <button type="submit">Validar documento</button>
    </form>

    <?php if ($error !== null): ?>
        <div class="alert danger" role="alert"><?= $escape($error) ?></div>
    <?php elseif (is_array($document)): ?>
        <?php
        $label = match ($document['tipo_documento']) {
            'certificado' => 'Certificado escolar',
            'declaracao_notas' => 'Declaração de notas',
            default => 'Declaração escolar'
        };
        ?>
        <div class="alert success" role="status"><strong>Documento autêntico</strong></div>
        <div class="details">
            <div><strong>Tipo:</strong> <?= $escape($label) ?></div>
            <div><strong>Escola:</strong> <?= $escape($document['escola_nome']) ?></div>
            <div><strong>Distrito:</strong> <?= $escape($document['escola_distrito']) ?></div>
            <div><strong>Emitido em:</strong> <?= $escape($document['emitido_em']) ?></div>
            <div><strong>Código:</strong> <?= $escape($document['codigo_validacao']) ?></div>
        </div>
        <div class="actions">
            <a class="button" href="<?= $escape($basePath) ?>/portal/documento?codigo=<?= $escape(urlencode($document['codigo_validacao'])) ?>">
                Descarregar PDF
            </a>
        </div>
    <?php endif; ?>

    <a class="back" href="<?= $escape($basePath) ?>/">Voltar ao portal</a>
</main>
</body>
</html>
