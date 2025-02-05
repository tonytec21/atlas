<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload e Visualização de Arquivos KML</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        h1 {
            text-align: center;
            color: #333;
        }
        form {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 20px;
        }
        input[type="file"] {
            margin: 10px 0;
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
        #map {
            height: 500px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <h1>Upload e Visualização de Arquivos KML</h1>
    <form id="uploadForm" action="upload_kml.php" method="POST" enctype="multipart/form-data">
        <input type="file" name="kmlFile" accept=".kml" required>
        <button type="submit">Upload</button>
    </form>
    <div id="map"></div>

    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script>
        // Inicializando o mapa
        const map = L.map('map').setView([0, 0], 2);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: 'Map data © OpenStreetMap contributors',
        }).addTo(map);

        // Função para calcular o centro de um polígono
        const calculatePolygonCenter = (coordinates) => {
            let latSum = 0, lngSum = 0;
            coordinates.forEach(([lat, lng]) => {
                latSum += lat;
                lngSum += lng;
            });
            return [latSum / coordinates.length, lngSum / coordinates.length];
        };

        // Função para limpar coordenadas
        const cleanCoordinates = (rawCoordinates) => {
            try {
                return rawCoordinates
                    .replace(/[\r\n\t]+/g, ' ') // Remove quebras de linha e tabs
                    .replace(/\s+/g, ' ') // Substitui múltiplos espaços por um único
                    .trim(); // Remove espaços no início e no final
            } catch (e) {
                console.error("Erro ao limpar coordenadas:", e);
                return '';
            }
        };

        // Fetch e exibição de dados do banco de dados
        const loadMapData = () => {
            fetch('get_kml_data.php')
                .then(response => response.json())
                .then(data => {
                    let bounds = [];
                    data.forEach(item => {
                        const cleanedCoordinates = cleanCoordinates(item.coordinates);
                        if (!cleanedCoordinates) return;

                        const coordinates = cleanedCoordinates
                            .split(' ')
                            .map(coord => {
                                const [lng, lat] = coord.split(',').map(Number);
                                return [lat, lng];
                            });

                        if (coordinates.length < 3) return;

                        // Criar polígono para a área
                        const polygon = L.polygon(coordinates, {
                            color: 'blue',
                            fillColor: '#3388ff',
                            fillOpacity: 0.5,
                        }).addTo(map);

                        polygon.bindPopup(`<strong>${item.name}</strong><br>Área demarcada.`);

                        // Adicionar marcador no centro do polígono
                        const center = calculatePolygonCenter(coordinates);
                        const marker = L.marker(center).addTo(map);
                        marker.bindPopup(`<strong>${item.name}</strong><br>Localização do imóvel.`);

                        // Atualizar limites do mapa
                        bounds.push(...coordinates);
                    });

                    if (bounds.length > 0) {
                        map.fitBounds(bounds);
                    }
                })
                .catch(err => console.error("Erro ao carregar dados do mapa:", err));
        };

        // Carregar dados no mapa
        loadMapData();

        // Interceptar o envio do formulário para exibir mensagens com SweetAlert2
        const form = document.getElementById('uploadForm');
        form.addEventListener('submit', (event) => {
            event.preventDefault();

            const formData = new FormData(form);
            fetch('upload_kml.php', {
                method: 'POST',
                body: formData,
            })
                .then(response => response.text())
                .then(result => {
                    Swal.fire({
                        icon: "success",
                        title: "Sucesso",
                        text: result,
                        confirmButtonColor: "#007bff",
                    }).then(() => {
                        map.eachLayer((layer) => {
                            if (layer instanceof L.Polygon || layer instanceof L.Marker) {
                                map.removeLayer(layer);
                            }
                        });
                        loadMapData(); // Recarregar o mapa com os novos dados
                    });
                })
                .catch(error => {
                    Swal.fire({
                        icon: "error",
                        title: "Erro",
                        text: "Ocorreu um problema ao processar o arquivo.",
                        confirmButtonColor: "#d33",
                    });
                });
        });
    </script>
</body>
</html>
