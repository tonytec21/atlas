<footer>
    <div class="footer-content">
        <p>
            <br><br>
            <!-- &copy; <span id="year"></span> Atlas | <a href="https://backupcloud.site/" target="_blank" style="color: inherit; text-decoration: none;">By Backup Cloud.</a> Todos os direitos reservados. -->
        </p>
    </div>
</footer>
<!-- <script>
    document.getElementById('year').textContent = new Date().getFullYear();
</script> -->
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
        /* background-color: #f8f9fa; */
        padding: 20px 0;
        text-align: center;
        /* border-top: 1px solid #e9ecef; */
        /* box-shadow: 0 -2px 5px rgba(0, 0, 0, 0.1); */
        width: 100%;
        margin-top: auto;
        position: relative;
        bottom: 0;
    }

    .footer-content a {
        color: #007bff; 
        font-size: 14px;
        transition: color 0.3s;
    }

    .footer-content a:hover {
        color: #0056b3; 
    }

    body.dark-mode footer {
        /* background-color: #343a40;  */
        /* color: #f8f9fa;  */
        /* border-top: 1px solid #454d55; */
    }

    body.dark-mode .footer-content a {
        color: #66b2ff; 
    }

    body.dark-mode .footer-content a:hover {
        color: #3399ff; 
    }
</style>
