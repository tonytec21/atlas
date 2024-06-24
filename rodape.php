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
</style>
