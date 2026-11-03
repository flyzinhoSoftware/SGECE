<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?></title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
</head>

<body class="bg-light">

    <main class="container py-5">

        <div class="card shadow-sm border-0">
            <div class="card-body text-center p-5">

                <h1 class="text-primary fw-bold">
                    <?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?>
                </h1>

                <p class="text-secondary mt-3">
                    <?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?>
                </p>

                <span class="badge bg-success">
                    Núcleo MVC activo
                </span>

            </div>
        </div>

    </main>

</body>
</html>