<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salvar Memorial Descritivo</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        h1 {
            text-align: center;
            color: #333;
        }
        form {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        textarea {
            width: 100%;
            height: 200px;
            margin: 10px 0;
            padding: 10px;
            font-size: 16px;
            border: 1px solid #ccc;
            border-radius: 5px;
            resize: vertical;
        }
        input[type="text"] {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            font-size: 16px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <h1>Salvar Memorial Descritivo</h1>
    <form id="memorialForm">
        <label for="name">Nome do Memorial:</label>
        <input type="text" id="name" name="name" placeholder="Digite o nome do memorial" required>

        <label for="coordinates">Memorial Descritivo:</label>
        <textarea id="coordinates" name="coordinates" placeholder="Cole o memorial descritivo aqui..." required></textarea>

        <button type="submit">Salvar</button>
    </form>

    <script>
        const form = document.getElementById('memorialForm');

        form.addEventListener('submit', (event) => {
            event.preventDefault();

            const formData = new FormData(form);

            fetch('save_memorial.php', {
                method: 'POST',
                body: formData,
            })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        Swal.fire({
                            icon: "success",
                            title: "Sucesso",
                            text: result.message,
                        }).then(() => {
                            form.reset(); // Limpar o formulário após salvar
                        });
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Erro",
                            text: result.message,
                        });
                    }
                })
                .catch(error => {
                    Swal.fire({
                        icon: "error",
                        title: "Erro",
                        text: "Ocorreu um problema ao salvar o memorial.",
                    });
                    console.error("Erro:", error);
                });
        });
    </script>
</body>
</html>
