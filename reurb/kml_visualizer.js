document.addEventListener('DOMContentLoaded', async () => {
    const map = L.map('map').setView([-15.7942, -47.8822], 5);

    // Adiciona camada base do mapa
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: 'Map data © OpenStreetMap contributors',
    }).addTo(map);

    // Função para limpar coordenadas
    const cleanCoordinates = (rawCoordinates) => {
        // Remove quebras de linha, tabs e espaços múltiplos
        return rawCoordinates
            .replace(/[\r\n\t]+/g, ' ') // Substitui quebras de linha e tabs por espaço
            .replace(/\s+/g, ' ') // Substitui múltiplos espaços por um único
            .trim(); // Remove espaços extras no início e no final
    };

    // Fetch data from server
    const response = await fetch('get_kml_data.php');
    const data = await response.json();

    data.forEach((item) => {
        const cleanedCoordinates = cleanCoordinates(item.coordinates);
        const coordinates = cleanedCoordinates
            .split(' ')
            .map(coord => {
                const [lng, lat] = coord.split(',').map(Number);
                return [lat, lng];
            });

        // Cria um polígono para a área
        const polygon = L.polygon(coordinates, {
            color: 'blue',
            fillColor: '#3388ff',
            fillOpacity: 0.5,
        }).addTo(map);

        polygon.bindPopup(`<b>${item.name}</b>`);
        map.fitBounds(polygon.getBounds()); // Ajusta a visualização para caber no polígono
    });
});
