<footer>
    <div class="footer-content">
        <p>
            &copy; <span id="year"></span> Atlas | By Backup Cloud. Todos os direitos reservados.
        </p>
    </div>
</footer>
<script>
    document.getElementById('year').textContent = new Date().getFullYear();
</script>
<style>
    body {
        margin: 0;
        padding: 0;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }

    main {
        flex: 1;
    }

    footer {
        background-color: #f8f9fa; /* Suave coloração diferente */
        padding: 20px 0;
        text-align: center;
        border-top: 1px solid #e9ecef;
        box-shadow: 0 -2px 5px rgba(0, 0, 0, 0.1);
        width: 100%;
        margin-top: auto;
        position: relative;
        bottom: 0;
    }

    .footer-content a {
        color: #007bff; /* Cor suave para o texto */
        font-size: 14px;
        transition: color 0.3s;
    }

    .footer-content a:hover {
        color: #0056b3; /* Cor mais escura ao passar o mouse */
    }

    body.dark-mode footer {
        background-color: #343a40; /* Cor de fundo para modo escuro */
        color: #f8f9fa; /* Cor do texto para modo escuro */
        border-top: 1px solid #454d55;
    }

    body.dark-mode .footer-content a {
        color: #66b2ff; /* Cor suave para o texto no modo escuro */
    }

    body.dark-mode .footer-content a:hover {
        color: #3399ff; /* Cor mais escura ao passar o mouse no modo escuro */
    }
</style>
