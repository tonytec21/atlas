<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Geração de Selos</title>
    <style>
        form {
            max-width: 600px;
            margin: 20px auto;
        }
        label {
            display: block;
            margin-bottom: 5px;
        }
        input, textarea, button, select {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        button {
            background-color: #007bff;
            color: white;
            border: none;
            cursor: pointer;
        }
        button:hover {
            background-color: #0056b3;        
        }
        .selo-gerado {
            max-width: 600px;
            margin: 20px auto;
            border: 1px solid #ddd;
            padding: 10px;
        }
        .selo-gerado table {
            width: 100%;
            border-collapse: collapse;
        }
        .selo-gerado td {
            padding: 0px;
        }
        .selo-gerado img {
            max-width: 100px;
            margin-top: 40px;
        }
    </style>
</head>
<body>
    <h1>Gerar Selo para Atos em Geral</h1>
    <form method="post">
        <label for="ato">Ato:</label>
        <select id="ato" name="ato" required>
            <option value="13.30">13.30 - Arquivamento, por folha do documento, os emolumentos serão: R$ 6,25</option>
            <option value="14.12">14.12 - Arquivamento, por folha do documento, os emolumentos serão: R$ 6,25</option>
            <option value="15.22">15.22 - Arquivamento, por folha do documento, os emolumentos serão: R$ 6,25</option>
            <option value="16.39">16.39 - Arquivamento, por folha do documento, os emolumentos serão: R$ 6,25</option>
            <option value="17.9">17.9 - Arquivamento, por folha do documento, os emolumentos serão: R$ 6,12</option>
            <option value="18.13">18.13 - Arquivamento, por folha do documento, os emolumentos serão: R$ 6,25</option>
        </select><br><br>

        <label for="escrevente">Escrevente:</label>
        <input type="text" id="escrevente" name="escrevente" required><br><br>

        <label for="partes">Partes:</label>
        <textarea id="partes" name="partes" required></textarea><br><br>

        <label for="quantidade">Quantidade:</label>
        <input type="number" id="quantidade" name="quantidade" required><br><br>

        <button type="submit">Solicitar Selo</button>
    </form>

    <?php include 'gerar_selo.php'; ?>

    <?php if (!empty($seloHtml)): ?>
        <div class="selo-gerado">
            <?php echo $seloHtml; ?>
        </div>
    <?php endif; ?>
</body>
</html>
