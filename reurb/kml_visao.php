<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Map Viewer</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        #map {
            height: 500px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <h1>Map Viewer</h1>
    <div id="map"></div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Inicialização do mapa
        var map = L.map('map').setView([0, 0], 2);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: 'Map data © OpenStreetMap contributors'
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

        // Função para limpar e validar coordenadas
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

        // Obtendo dados do banco de dados
        fetch('get_kml_data.php')
            .then(response => response.json())
            .then(data => {
                let bounds = [];
                data.forEach(item => {
                    // Limpar coordenadas antes de processar
                    const cleanedCoordinates = cleanCoordinates(item.coordinates);
                    if (!cleanedCoordinates) return; // Ignorar entradas inválidas

                    const coordinates = cleanedCoordinates
                        .split(' ')
                        .map(coord => {
                            const [lng, lat] = coord.split(',').map(Number);
                            return [lat, lng]; // Leaflet usa [lat, lng]
                        });

                    if (coordinates.length < 3) return; // Um polígono deve ter pelo menos 3 pontos

                    // Criar um polígono para a área
                    const polygon = L.polygon(coordinates, {
                        color: 'blue',
                        fillColor: '#3388ff',
                        fillOpacity: 0.5
                    }).addTo(map);

                    polygon.bindPopup(`<strong>${item.name}</strong><br>Área demarcada.`);

                    // Calcular o centro do polígono
                    const center = calculatePolygonCenter(coordinates);

                    // Adicionar um marcador na localização central do polígono
                    const marker = L.marker(center).addTo(map);
                    marker.bindPopup(`<strong>${item.name}</strong><br>Localização do imóvel.`);

                    // Atualizar os limites do mapa
                    bounds.push(...coordinates);
                });

                // Ajustar o mapa para mostrar todos os marcadores e polígonos
                if (bounds.length > 0) {
                    map.fitBounds(bounds);
                }
            })
            .catch(err => console.error("Erro ao obter dados:", err));
    </script>
</body>
</html>
