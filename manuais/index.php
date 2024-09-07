<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vídeos Tutoriais</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../style/css/materialdesignicons.min.css">
    <style>
        .category-title {
            margin-top: 20px;
            margin-bottom: 10px;
            cursor: pointer;
        }

        .video-card {
            margin-bottom: 20px;
            position: relative;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .video-card-title {
            background-color: #313131;
            color: white;
            text-align: center;
            height: 55px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            border-radius: 5px 5px 0 0;
            padding: 10px;
        }

        .video-card iframe {
            width: 100%;
            height: 250px;
            border-radius: 0 0 5px 5px;
            display: none;
        }

        .video-placeholder {
            width: 100%;
            height: 250px;
            background: #000;
            position: relative;
            cursor: pointer;
            border-radius: 0 0 5px 5px;
        }

        .video-placeholder i {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 50px;
        }

        .video-description {
            font-size: 14px;
            line-height: 1.2;
            text-align: justify;
            padding: 10px;
            min-height: 60px;
        }

        .search-bar {
            margin-bottom: 20px;
        }

        .category-section {
            margin-bottom: 20px;
        }

        .card-body {
            padding: 0;
            border-radius: 5px;
        }
    </style>
</head>

<body class="light-mode">
    <?php
    include(__DIR__ . '/../menu.php');
    ?>

    <div id="main" class="main-content">
        <div class="container">
            <h3>Vídeos Tutoriais</h3>
            <hr>
            <div class="search-bar">
                <input type="text" id="searchInput" class="form-control" placeholder="Pesquisar vídeos...">
            </div>
            <hr>
            <?php
            $conn = getDatabaseConnection();
            $stmt = $conn->query("SELECT DISTINCT categoria FROM manuais WHERE status = 'ativo' ORDER BY categoria ASC");
            $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($categorias as $categoria) {
                echo '<div class="category-section">';
                echo '<h5 class="category-title" data-toggle="collapse" data-target="#category-' . md5($categoria['categoria']) . '">' . htmlspecialchars($categoria['categoria']) . ' <i class="fa fa-chevron-down"></i></h5>';
                
                $stmtVideos = $conn->prepare("SELECT * FROM manuais WHERE categoria = :categoria AND status = 'ativo' ORDER BY ordem ASC");
                $stmtVideos->bindParam(':categoria', $categoria['categoria']);
                $stmtVideos->execute();
                $videos = $stmtVideos->fetchAll(PDO::FETCH_ASSOC);

                echo '<div id="category-' . md5($categoria['categoria']) . '" class="collapse show">';
                echo '<div class="row">';
                foreach ($videos as $video) {
                    echo '<div class="col-md-4 video-card">';
                    echo '<div class="card">';
                    echo '<div class="card-body">';
                    echo '<div class="video-card-title">' . htmlspecialchars($video['titulo']) . '</div>';
                    echo '<div class="video-placeholder" data-src="' . htmlspecialchars($video['caminho_video']) . '">';
                    echo '<i class="fa fa-play-circle"></i>';
                    echo '</div>';
                    echo '<iframe allow="autoplay; encrypted-media" frameborder="0" allowfullscreen></iframe>';
                    echo '<p class="video-description">' . htmlspecialchars($video['descricao']) . '</p>';
                    echo '</div>';
                    echo '</div>';
                    echo '</div>';
                }
                echo '</div>'; // End of row
                echo '</div>'; // End of collapse
                echo '</div>'; // End of category-section
            }
            ?>
        </div>
    </div>

    <script src="../script/jquery-3.5.1.min.js"></script>
    <script src="../script/bootstrap.min.js"></script>
    <script src="../script/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#searchInput').on('keyup', function() {
                var value = $(this).val().toLowerCase();
                $('.category-section').each(function() {
                    var section = $(this);
                    var found = false;
                    section.find('.video-card').each(function() {
                        var videoTitle = $(this).find('.video-card-title').text().toLowerCase();
                        var videoDescription = $(this).find('.video-description').text().toLowerCase();
                        if (videoTitle.includes(value) || videoDescription.includes(value)) {
                            $(this).show();
                            found = true;
                        } else {
                            $(this).hide();
                        }
                    });
                    if (found) {
                        section.show();
                        section.find('.collapse').collapse('show');
                    } else {
                        section.hide();
                    }
                });
            });

            $('.category-title').on('click', function() {
                var icon = $(this).find('i');
                icon.toggleClass('fa-chevron-down fa-chevron-up');
            });

            $('.video-placeholder').on('click', function() {
                var iframe = $(this).next('iframe');
                var videoSrc = $(this).data('src');
                iframe.attr('src', videoSrc).show();
                $(this).hide();
            });
        });
    </script>
    <?php
    include(__DIR__ . '/../rodape.php');
    ?>
</body>

</html>
