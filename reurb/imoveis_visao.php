<!DOCTYPE html>  
<html lang="pt-BR">  
<head>  
    <meta charset="UTF-8">  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">  
    <title>Visualizador de Imóveis</title>  
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />  
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">  
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">  
    <script src="https://cdnjs.cloudflare.com/ajax/libs/proj4js/2.7.5/proj4.js"></script>  
    
    <style>  
        * {  
            margin: 0;  
            padding: 0;  
            box-sizing: border-box;  
            font-family: 'Poppins', sans-serif;  
        }  

        body {  
            background-color: #f5f7fa;  
            color: #2d3436;  
        }  

        .container {  
            max-width: 1400px;  
            margin: 0 auto;  
            padding: 20px;  
        }  

        .header {  
            background: linear-gradient(135deg, #6c5ce7, #a363d9);  
            color: white;  
            padding: 20px 0;  
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);  
            margin-bottom: 30px;  
        }  

        .header-content {  
            display: flex;  
            justify-content: space-between;  
            align-items: center;  
            padding: 0 20px;  
        }  

        h1 {  
            font-size: 2em;  
            font-weight: 600;  
        }  

        .stats-container {  
            display: grid;  
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));  
            gap: 20px;  
            margin-bottom: 20px;  
        }  

        .stat-card {  
            background: white;  
            padding: 20px;  
            border-radius: 10px;  
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);  
            transition: transform 0.3s ease;  
        }  

        .stat-card:hover {  
            transform: translateY(-5px);  
        }  

        .stat-card i {  
            font-size: 24px;  
            color: #6c5ce7;  
            margin-bottom: 10px;  
        }  

        .error-card {  
            border-left: 4px solid #ff6b6b;  
        }  

        .error-card i {  
            color: #ff6b6b !important;  
        }  

        .stat-value {  
            font-size: 24px;  
            font-weight: 600;  
            color: #2d3436;  
        }  

        .stat-label {  
            color: #636e72;  
            font-size: 14px;  
        }  

        #map {  
            height: 70vh;  
            width: 100%;  
            border-radius: 15px;  
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);  
            margin-bottom: 30px;  
            z-index: 1;  
        }  

        .leaflet-popup-content {  
            font-family: 'Poppins', sans-serif;  
        }  

        .popup-content {  
            padding: 10px;  
        }  

        .popup-title {  
            font-weight: 600;  
            color: #2d3436;  
            margin-bottom: 5px;  
        }  

        .popup-info {  
            color: #636e72;  
            font-size: 14px;  
        }  

        .popup-content .popup-title {  
            font-weight: bold;  
            margin-bottom: 5px;  
        }  

        .popup-content ul {  
            margin: 5px 0;  
            padding-left: 20px;  
        }  

        .popup-content li {  
            margin: 2px 0;  
        }



        .popup-content {  
            padding: 5px;  
        }  

        .popup-content .popup-title {  
            font-weight: bold;  
            margin-bottom: 5px;  
            color: #333;  
        }  

        .popup-content .popup-info {  
            font-size: 0.9em;  
            color: #666;  
        }  

        .popup-content ul {  
            margin: 5px 0;  
            padding-left: 20px;  
            list-style-type: disc;  
        }  

        .popup-content li {  
            margin: 2px 0;  
        }  

        .error-item {  
            padding: 10px;  
            margin: 5px 0;  
            border: 1px solid #ddd;  
            border-radius: 4px;  
            background-color: #f9f9f9;  
        }


        .error-details {  
            background: white;  
            border-radius: 10px;  
            padding: 20px;  
            margin: 20px 0;  
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);  
            display: none;  
        }  

        .error-title {  
            color: #ff6b6b;  
            font-size: 1.2em;  
            margin-bottom: 15px;  
            display: flex;  
            align-items: center;  
            gap: 10px;  
        }  

        .error-list {  
            max-height: 300px;  
            overflow-y: auto;  
            padding-right: 10px;  
        }  

        .error-item {  
            padding: 10px;  
            border-bottom: 1px solid #eee;  
            font-size: 14px;  
        }  

        .error-item:last-child {  
            border-bottom: none;  
        }  

        @media (max-width: 768px) {  
            .container {  
                padding: 10px;  
            }  

            h1 {  
                font-size: 1.5em;  
            }  

            #map {  
                height: 50vh;  
            }  
        }  
    </style>  
</head>  
<body>  
    <div class="header">  
        <div class="header-content">  
            <h1><i class="fas fa-map-marked-alt"></i> Visualizador de Imóveis</h1>  
        </div>  
    </div>  

    <div class="container">  
        <div class="stats-container">  
            <div class="stat-card">  
                <i class="fas fa-home"></i>  
                <div class="stat-value" id="total-properties">0</div>  
                <div class="stat-label">Total de Imóveis</div>  
            </div>  
            <div class="stat-card">  
                <i class="fas fa-map-marker-alt"></i>  
                <div class="stat-value" id="valid-properties">0</div>  
                <div class="stat-label">Imóveis no Mapa</div>  
            </div>  
            <div class="stat-card error-card">  
                <i class="fas fa-exclamation-triangle"></i>  
                <div class="stat-value" id="invalid-properties">0</div>  
                <div class="stat-label">Coordenadas Inválidas</div>  
            </div>  
            <div class="stat-card">  
                <i class="fas fa-chart-area"></i>  
                <div class="stat-value" id="total-area">0</div>  
                <div class="stat-label">Área Total (m²)</div>  
            </div>  
            <div class="stat-card">  
                <i class="fas fa-users"></i>  
                <div class="stat-value" id="total-owners">0</div>  
                <div class="stat-label">Proprietários</div>  
            </div>  
        </div>  

        <div id="map"></div>  
        <div id="error-details" class="error-details">  
            <div class="error-title">  
                <i class="fas fa-exclamation-circle"></i>  
                <span>Detalhes dos Imóveis com Coordenadas Inválidas</span>  
            </div>  
            <div id="error-list" class="error-list"></div>  
        </div>  
    </div>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>  
    <script src='https://unpkg.com/@turf/turf@6/turf.min.js'></script>
    <script>  
    // Inicialização do mapa  
const map = L.map('map').setView([0, 0], 2);  

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {  
    maxZoom: 19,  
    attribution: 'Map data © OpenStreetMap contributors',  
}).addTo(map);  

// Configuração do Proj4 para converter UTM para WGS84  
const utmToWGS84 = "+proj=utm +zone=23 +south +datum=WGS84 +units=m +no_defs";  
const wgs84 = "+proj=longlat +datum=WGS84 +no_defs";  

// Função para validar coordenadas geográficas  
const isValidLatLng = (lat, lng) => lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180;  

// Função para calcular área usando coordenadas UTM  
const calculateAreaFromUTM = (utmCoordinatesString) => {  
    if (!utmCoordinatesString) return 0;  

    const utmPoints = utmCoordinatesString.split(' ').map(coord => {  
        const [x, y] = coord.split(',').map(Number);  
        return { x, y };  
    });  

    if (utmPoints.length < 3) return 0;  

    let area = 0;  
    for (let i = 0; i < utmPoints.length; i++) {  
        const j = (i + 1) % utmPoints.length;  
        area += (utmPoints[i].x * utmPoints[j].y) - (utmPoints[j].x * utmPoints[i].y);  
    }  

    return Math.abs(area) / 2;  
};  

// Função para verificar sobreposição entre dois polígonos  
const polygonsOverlap = (poly1Coords, poly2Coords) => {  
    try {  
        const poly1 = turf.polygon([poly1Coords.map(coord => [coord[1], coord[0]])]);  
        const poly2 = turf.polygon([poly2Coords.map(coord => [coord[1], coord[0]])]);  
        const overlap = turf.intersect(poly1, poly2);  
        return overlap !== null;  
    } catch (error) {  
        console.warn('Erro ao verificar sobreposição:', error);  
        return false;  
    }  
};  

// Função para calcular a área de sobreposição  
const calculateOverlapArea = (poly1Coords, poly2Coords) => {  
    try {  
        const poly1 = turf.polygon([poly1Coords.map(coord => [coord[1], coord[0]])]);  
        const poly2 = turf.polygon([poly2Coords.map(coord => [coord[1], coord[0]])]);  
        const intersection = turf.intersect(poly1, poly2);  
        return intersection ? turf.area(intersection) : 0;  
    } catch (error) {  
        console.warn('Erro ao calcular área de sobreposição:', error);  
        return 0;  
    }  
};  

// Função para converter coordenadas UTM para latitude/longitude  
const convertUTMtoLatLng = (utmCoordinates) => {  
    if (!utmCoordinates) return [];  
    
    return utmCoordinates.split(' ').map(coord => {  
        const [x, y] = coord.split(',').map(Number);  
        if (isNaN(x) || isNaN(y)) return null;  

        try {  
            const [lng, lat] = proj4(utmToWGS84, wgs84, [x, y]);  
            return isValidLatLng(lat, lng) ? [lat, lng] : null;  
        } catch (error) {  
            console.warn(`Erro ao converter coordenada UTM: ${coord}`, error);  
            return null;  
        }  
    }).filter(coord => coord !== null);  
};  

// Função para calcular o centro de um polígono  
const calculatePolygonCenter = (coordinates) => {  
    let latSum = 0, lngSum = 0;  
    coordinates.forEach(([lat, lng]) => {  
        latSum += lat;  
        lngSum += lng;  
    });  
    return [latSum / coordinates.length, lngSum / coordinates.length];  
};  

// Variável para armazenar o polígono destacado  
let highlightedPolygon = null;  

// Função para criar popup personalizado  
const createCustomPopup = (proprietario, area) => {  
    return `  
        <div class="popup-content">  
            <div class="popup-title">${proprietario}</div>  
            <div class="popup-info">Área: ${Math.round(area).toLocaleString()},00 m²</div>  
        </div>  
    `;  
};  

// Função para criar popup de sobreposição  
const createOverlapPopup = (properties) => {  
    return `  
        <div class="popup-content">  
            <div class="popup-title">Área Sobreposta</div>  
            <div class="popup-info">  
                Proprietários envolvidos:  
                <ul>  
                    ${properties.owners.map(owner => `<li>${owner}</li>`).join('')}  
                </ul>  
                Área sobreposta: ${Math.round(properties.overlapArea).toLocaleString()},00 m²  
            </div>  
        </div>  
    `;  
};
// Função para atualizar estatísticas  
const updateStats = (data, validCount, invalidProperties, overlappingCount = 0) => {  
    let totalArea = 0;  
    const uniqueOwners = new Set();  

    data.forEach(item => {  
        if (item.coordinates) {  
            const area = calculateAreaFromUTM(item.coordinates);  
            totalArea += area;  
            uniqueOwners.add(item.proprietario_nome);  
        }  
    });  

    document.getElementById('total-properties').textContent = data.length;  
    document.getElementById('valid-properties').textContent = validCount;  
    document.getElementById('invalid-properties').textContent = invalidProperties.length;  
    document.getElementById('total-area').textContent = Math.round(totalArea).toLocaleString();  
    document.getElementById('total-owners').textContent = uniqueOwners.size;  
    
    // Adiciona contagem de sobreposições se o elemento existir  
    const overlappingElement = document.getElementById('overlapping-properties');  
    if (overlappingElement) {  
        overlappingElement.textContent = overlappingCount;  
    }  

    // Atualizar detalhes dos erros  
    const errorDetails = document.getElementById('error-details');  
    const errorList = document.getElementById('error-list');  

    if (invalidProperties.length > 0) {  
        errorList.innerHTML = invalidProperties.map(prop => `  
            <div class="error-item">  
                <strong>Imóvel ${prop.index}</strong><br>  
                Proprietário: ${prop.proprietario}<br>  
                Coordenadas: ${prop.coordinates || 'Não definidas'}  
            </div>  
        `).join('');  
        errorDetails.style.display = 'block';  
    } else {  
        errorDetails.style.display = 'none';  
    }  
};  

// Carregar dados dos imóveis  
const loadImoveis = () => {  
    fetch('get_imoveis_data.php')  
        .then(response => response.json())  
        .then(data => {  
            let bounds = [];  
            let validCount = 0;  
            let invalidProperties = [];  
            let polygons = [];  
            let overlappingCount = 0;  

            // Primeiro passo: criar todos os polígonos  
            data.forEach((item, index) => {  
                const coordinates = convertUTMtoLatLng(item.coordinates);  
                const areaUTM = calculateAreaFromUTM(item.coordinates);  

                if (!coordinates || coordinates.length < 3) {  
                    invalidProperties.push({  
                        index: index + 1,  
                        proprietario: item.proprietario_nome,  
                        coordinates: item.coordinates  
                    });  
                    return;  
                }  

                validCount++;  
                const polygon = L.polygon(coordinates, {  
                    color: 'green',  
                    fillColor: '#66ff66',  
                    fillOpacity: 0.5,  
                    original_color: 'green'  
                }).addTo(map);  

                polygon.proprietario = item.proprietario_nome;  
                polygon.coordinates = coordinates;  
                polygons.push(polygon);  

                const center = calculatePolygonCenter(coordinates);  
                const marker = L.marker(center).addTo(map);  

                const popupContent = createCustomPopup(item.proprietario_nome, areaUTM);  
                polygon.bindPopup(popupContent);  
                marker.bindPopup(popupContent);  

                bounds.push(...coordinates);  
            });  

            // Segundo passo: verificar sobreposições  
            const processedPairs = new Set();  

            for (let i = 0; i < polygons.length; i++) {  
                for (let j = i + 1; j < polygons.length; j++) {  
                    const pairKey = `${i}-${j}`;  
                    if (processedPairs.has(pairKey)) continue;  

                    if (polygonsOverlap(polygons[i].coordinates, polygons[j].coordinates)) {  
                        overlappingCount++;  
                        processedPairs.add(pairKey);  

                        // Marcar polígonos sobrepostos em vermelho  
                        [polygons[i], polygons[j]].forEach(polygon => {  
                            polygon.setStyle({  
                                color: 'red',  
                                fillColor: '#ff6666',  
                                fillOpacity: 0.5,  
                                original_color: 'red'  
                            });  
                        });  

                        // Criar polígono da área sobreposta  
                        try {  
                            const poly1 = turf.polygon([polygons[i].coordinates.map(coord => [coord[1], coord[0]])]);  
                            const poly2 = turf.polygon([polygons[j].coordinates.map(coord => [coord[1], coord[0]])]);  
                            const intersection = turf.intersect(poly1, poly2);  

                            if (intersection) {  
                                const overlapArea = turf.area(intersection);  
                                const intersectionCoords = intersection.geometry.coordinates[0]  
                                    .map(coord => [coord[1], coord[0]]);  

                                const overlapPolygon = L.polygon(intersectionCoords, {  
                                    color: 'red',  
                                    fillColor: '#ff0000',  
                                    fillOpacity: 0.7,  
                                    weight: 2  
                                }).addTo(map);  

                                const overlapProperties = {  
                                    owners: [polygons[i].proprietario, polygons[j].proprietario],  
                                    overlapArea: overlapArea  
                                };  

                                overlapPolygon.bindPopup(createOverlapPopup(overlapProperties));  
                            }  
                        } catch (error) {  
                            console.warn('Erro ao criar polígono de sobreposição:', error);  
                        }  
                    }  
                }  
            }  

            // Função para destacar polígono  
            const highlightPolygon = (polygon) => {  
                if (highlightedPolygon) {  
                    const originalColor = highlightedPolygon.options.original_color;  
                    highlightedPolygon.setStyle({  
                        color: originalColor,  
                        fillColor: originalColor === 'green' ? '#66ff66' : '#ff6666',  
                        fillOpacity: 0.5,  
                    });  
                }  
                polygon.setStyle({  
                    color: 'blue',  
                    fillColor: '#3388ff',  
                    fillOpacity: 0.7,  
                });  
                highlightedPolygon = polygon;  
            };  

            // Adicionar evento de clique aos polígonos  
            polygons.forEach(polygon => {  
                polygon.on('click', () => highlightPolygon(polygon));  
            });  

            if (bounds.length > 0) {  
                map.fitBounds(bounds);  
            }  

            updateStats(data, validCount, invalidProperties, overlappingCount);  
        })  
        .catch(err => {  
            console.error("Erro ao carregar dados dos imóveis:", err);  
            alert("Erro ao carregar dados dos imóveis. Por favor, tente novamente mais tarde.");  
        });  
};  

// Iniciar carregamento dos imóveis  
loadImoveis(); 
    </script>  
</body>  
</html>