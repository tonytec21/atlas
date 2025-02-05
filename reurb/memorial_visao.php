<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Memorial Viewer</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/proj4js/2.7.5/proj4.js"></script>
    <style>
        #map {
            height: 500px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <h1>Memorial Viewer</h1>
    <div id="map"></div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Inicialização do mapa
        const map = L.map('map').setView([0, 0], 2);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: 'Map data © OpenStreetMap contributors'
        }).addTo(map);

        // Configuração do Proj4 para converter UTM para WGS84
        const utmToWGS84 = "+proj=utm +zone=23 +south +datum=WGS84 +units=m +no_defs";
        const wgs84 = "+proj=longlat +datum=WGS84 +no_defs";

        // Função para validar coordenadas geográficas
        const isValidLatLng = (lat, lng) => {
            return lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180;
        };

        // Função para converter coordenadas UTM para geográficas
        const convertUTMtoLatLng = (utmCoordinates) => {
            return utmCoordinates.split(' ').map(coord => {
                const [x, y] = coord.split(',').map(Number);
                if (isNaN(x) || isNaN(y)) {
                    console.warn(`Coordenada inválida ignorada: ${coord}`);
                    return null;
                }
                try {
                    const [lng, lat] = proj4(utmToWGS84, wgs84, [x, y]);
                    return isValidLatLng(lat, lng) ? [lat, lng] : null;
                } catch (error) {
                    console.warn(`Erro ao converter coordenada UTM: ${coord}`, error);
                    return null;
                }
            }).filter(coord => coord !== null);
        };

        // Função para carregar dados do memorial do banco de dados
        const loadMemorialData = () => {
            fetch('get_memorial_data.php')
                .then(response => response.json())
                .then(data => {
                    let bounds = [];
                    data.forEach(item => {
                        const coordinates = convertUTMtoLatLng(item.coordinates);

                        if (coordinates.length < 3) return; // Ignorar polígonos inválidos

                        // Criar polígono no mapa
                        const polygon = L.polygon(coordinates, {
                            color: 'red',
                            fillColor: '#ff6666',
                            fillOpacity: 0.5,
                        }).addTo(map);

                        polygon.bindPopup(`<strong>${item.name}</strong><br>Área demarcada.`);
                        bounds.push(...coordinates);
                    });

                    if (bounds.length > 0) {
                        map.fitBounds(bounds);
                    }
                })
                .catch(err => console.error("Erro ao carregar dados do memorial:", err));
        };

        // Carregar dados no mapa
        loadMemorialData();
    </script>
</body>
</html>
