<?php
include(__DIR__ . '/session_check.php');
checkSession();
date_default_timezone_set('America/Sao_Paulo');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Atlas - Cadastro de Arquivamento</title>
  <link rel="stylesheet" href="../style/css/bootstrap.min.css">
  <link rel="stylesheet" href="../style/css/font-awesome.min.css">
  <link rel="stylesheet" href="../style/css/style.css">
  <link rel="icon" href="../style/img/favicon.png" type="image/png">
  <link rel="stylesheet" href="../style/sweetalert2.min.css">
  <?php include(__DIR__ . '/../style/style_cadastro_arquivamento.php');?>
  <style>
    textarea.form-control {
        height: 170px;
    }
  </style>
</head>
<body class="light-mode">
<?php include(__DIR__ . '/../menu.php'); ?>

  <div id="main" class="main-content">
    <div class="container">

      <!-- HERO -->
      <section class="page-hero">
        <div class="title-row">
          <div class="title-icon"><i class="fa fa-archive"></i></div>
          <div>
            <h1>Cadastro de Arquivamento</h1>
            <div class="subtitle muted">Insira um novo registro com partes envolvidas, detalhes e anexos.</div>
          </div>
        </div>
      </section>

      <form id="ato-form" class="needs-validation" novalidate>
        <!-- DADOS BÁSICOS -->
        <div class="form-card">
          <h4>Dados do Ato</h4>
          <div class="row g-grid">
            <div class="col-md-3">
              <label for="atribuicao">Atribuição</label>
              <select id="atribuicao" name="atribuicao" class="form-control" required>
                <option value="">Selecione</option>
                <option value="Registro Civil">Registro Civil</option>
                <option value="Registro de Imóveis">Registro de Imóveis</option>
                <option value="Registro de Títulos e Documentos">Registro de Títulos e Documentos</option>
                <option value="Registro Civil das Pessoas Jurídicas">Registro Civil das Pessoas Jurídicas</option>
                <option value="Notas">Notas</option>
                <option value="Protesto">Protesto</option>
                <option value="Contratos Marítimos">Contratos Marítimos</option>
              </select>
            </div>
            <div class="col-md-3">
              <label for="categoria">Categoria</label>
              <select id="categoria" name="categoria" class="form-control" required>
                <option value="">Selecione</option>
              </select>
            </div>
            <div class="col-md-3">
              <label for="data_ato">Data do Ato</label>
              <input type="date" class="form-control" id="data_ato" name="data_ato" required>
            </div>
            <div class="col-md-3">
              <label for="livro">Livro</label>
              <input type="text" class="form-control" id="livro" name="livro" placeholder="Opcional">
            </div>
            <div class="col-md-3">
              <label for="folha">Folha</label>
              <input type="text" class="form-control" id="folha" name="folha" placeholder="Opcional">
            </div>
            <div class="col-md-3">
              <label for="termo">Termo/Ordem</label>
              <input type="text" class="form-control" id="termo" name="termo" placeholder="Opcional">
            </div>
            <div class="col-md-3">
              <label for="protocolo">Protocolo</label>
              <input type="text" class="form-control" id="protocolo" name="protocolo" placeholder="Opcional">
            </div>
            <div class="col-md-3">
              <label for="matricula">Matrícula</label>
              <input type="text" class="form-control" id="matricula" name="matricula" placeholder="Opcional">
            </div>
          </div>
        </div>

        <!-- PARTES ENVOLVIDAS -->
        <div class="form-card">
          <h4>Parte Envolvida</h4>
          <div class="row g-grid">
            <div class="col-md-4">
              <label for="cpf">CPF/CNPJ</label>
              <input type="text" class="form-control" id="cpf" placeholder="CPF/CNPJ">
            </div>
            <div class="col-md-8">
              <label for="nome">Nome</label>
              <input type="text" class="form-control" id="nome" placeholder="Nome completo">
            </div>
          </div>
          <div class="actions-bar">
            <button type="button" class="btn btn-secondary" id="adicionar-parte">
              <i class="fa fa-user-plus"></i> Adicionar Parte
            </button>
          </div>

          <div class="table-responsive mt-3">
            <table class="table mb-0">
              <thead>
              <tr>
                <th style="min-width:140px">CPF/CNPJ</th>
                <th>Nome</th>
                <th style="width:80px">Ação</th>
              </tr>
              </thead>
              <tbody id="partes-envolvidas"></tbody>
            </table>
          </div>
        </div>

        <!-- DESCRIÇÃO -->
        <div class="form-card">
          <h4>Descrição e Detalhes</h4>
          <div class="form-group">
            <label for="descricao">Descrição e Detalhes</label>
            <textarea class="form-control" id="descricao" name="descricao" rows="3" placeholder="Descreva brevemente o ato"></textarea>
          </div>
        </div>

        <!-- ANEXOS -->
        <div class="form-card">
          <h4>Anexos</h4>
          <!-- Dropzone -->
          <div id="dropzone" class="dropzone" tabindex="0" role="button" aria-label="Área para arrastar e soltar arquivos">
            <i class="fa fa-cloud-upload dz-icon" aria-hidden="true"></i>
            <div class="dz-title">Arraste e solte os arquivos aqui</div>
            <div class="dz-help">ou</div>
            <button type="button" id="btnBrowse" class="dz-btn">
              <i class="fa fa-folder-open"></i> Selecionar arquivos
            </button>
            <!-- input “real”, escondido (apenas para abrir seletor; não depende do value no submit) -->
            <input type="file" id="file-input" name="file-input[]" multiple class="sr-only" hidden>
          </div>

          <!-- Lista de arquivos -->
          <div id="filesList" class="files-list" aria-live="polite"></div>
        </div>

        <button type="submit" class="btn btn-primary w-100" style="margin-top:0px; margin-bottom: 30px;">
          <i class="fa fa-save"></i> Salvar e Concluir
        </button>
      </form>
    </div>
  </div>

  <script src="../script/jquery-3.5.1.min.js"></script>
  <script src="../script/bootstrap.min.js"></script>
  <script src="../script/jquery.mask.min.js"></script>
  <script src="../script/sweetalert2.js"></script>
  <script>
    // ======================== Helpers ========================
    function bytesToSize(bytes){
      if (bytes === 0) return '0 B';
      const k = 1024, sizes = ['B','KB','MB','GB','TB'];
      const i = Math.floor(Math.log(bytes)/Math.log(k));
      return parseFloat((bytes/Math.pow(k,i)).toFixed(2)) + ' ' + sizes[i];
    }
    function extIcon(ext){
      ext = (ext||'').toLowerCase();
      if(['jpg','jpeg','png','gif','webp','bmp','tiff'].includes(ext)) return 'fa-file-image-o';
      if(['pdf'].includes(ext)) return 'fa-file-pdf-o';
      if(['doc','docx'].includes(ext)) return 'fa-file-word-o';
      if(['xls','xlsx','csv'].includes(ext)) return 'fa-file-excel-o';
      if(['ppt','pptx'].includes(ext)) return 'fa-file-powerpoint-o';
      if(['zip','rar','7z'].includes(ext)) return 'fa-file-archive-o';
      if(['txt','md','rtf'].includes(ext)) return 'fa-file-text-o';
      return 'fa-file-o';
    }
    function getExt(name){ return (name.split('.').pop()||'').toLowerCase(); }

    // ======================== VALIDADORES CPF/CNPJ ========================
    function validarCPF_CNPJ(value) {
      // Mantem compatibilidade com máscaras diferentes (já que no input aplicamos após blur)
      const digitsOnly = (value||'').replace(/[^\d]/g,'');
      if (!digitsOnly) return false;

      // CPF
      if (digitsOnly.length === 11) {
        if (/^(\d)\1{10}$/.test(digitsOnly)) return false;
        let soma=0, resto;
        for (let i=1;i<=9;i++) soma += parseInt(digitsOnly.substring(i-1,i))*(11-i);
        resto = (soma*10)%11; if (resto===10||resto===11) resto=0;
        if (resto!==parseInt(digitsOnly.substring(9,10))) return false;
        soma=0;
        for (let i=1;i<=10;i++) soma+= parseInt(digitsOnly.substring(i-1,i))*(12-i);
        resto = (soma*10)%11; if (resto===10||resto===11) resto=0;
        if (resto!==parseInt(digitsOnly.substring(10,11))) return false;
        return true;
      }

      // CNPJ
      if (digitsOnly.length === 14) {
        if (/^(\d)\1{13}$/.test(digitsOnly)) return false;
        let tamanho = digitsOnly.length-2;
        let numeros = digitsOnly.substring(0,tamanho);
        let digitos = digitsOnly.substring(tamanho);
        let soma = 0, pos = tamanho-7;
        for (let i=tamanho; i>=1; i--){
          soma += numeros.charAt(tamanho-i)*pos--; if (pos<2) pos=9;
        }
        let resultado = soma%11<2?0:11-soma%11;
        if (resultado!==parseInt(digitos.charAt(0))) return false;

        tamanho = tamanho+1; numeros = digitsOnly.substring(0,tamanho); soma=0; pos=tamanho-7;
        for (let i=tamanho; i>=1; i--){
          soma += numeros.charAt(tamanho-i)*pos--; if (pos<2) pos=9;
        }
        resultado = soma%11<2?0:11-soma%11;
        if (resultado!==parseInt(digitos.charAt(1))) return false;
        return true;
      }
      return false;
    }

    $(function(){
      // ======================== Máscara CPF/CNPJ ========================
      $('#cpf').on('blur', function() {
        var value = $(this).val().replace(/\D/g, '');
        if (value.length === 11) $(this).mask('000.000.000-00', { reverse: true });
        else if (value.length === 14) $(this).mask('00.000.000/0000-00', { reverse: true });
        else $(this).unmask();
      });

      // ======================== Carrega Categorias ========================
      $.ajax({
        url: 'categorias/categorias.json',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
          var categoriaSelect = $('#categoria');
          categoriaSelect.empty().append('<option value="">Selecione</option>');
          response.forEach(function(categoria) {
            categoriaSelect.append($('<option>', { value: categoria, text: categoria }));
          });
        }
      });

      // ======================== Gerenciar Partes ========================
      $('#adicionar-parte').on('click', function(){
        var cpf = $('#cpf').val();
        var nome = $('#nome').val().trim();

        if (!nome){
          Swal.fire({ icon:'warning', title:'Atenção!', text:'Preencha o nome.', confirmButtonText:'OK' });
          return;
        }
        if (cpf && !validarCPF_CNPJ(cpf)){
          Swal.fire({ icon:'error', title:'Erro!', text:'CPF/CNPJ inválido.', confirmButtonText:'OK' });
          return;
        }

        var row = '<tr>'+
          '<td>'+ (cpf || '') +'</td>'+
          '<td>'+ nome +'</td>'+
          '<td><button class="btn btn-delete btn-sm remover-parte" type="button" title="Remover"><i class="fa fa-trash" aria-hidden="true"></i></button></td>'+
        '</tr>';
        $('#partes-envolvidas').append(row);
        $('#cpf').val(''); $('#nome').val('').focus();
      });

      $(document).on('click','.remover-parte', function(){
        $(this).closest('tr').remove();
      });

      // ======================== Dropzone ========================
      const $drop = $('#dropzone');
      const $browse = $('#btnBrowse');
      const $input = $('#file-input');
      const $list = $('#filesList');

      // array de arquivos em memória para enviar via FormData
      let dzFiles = [];

      function renderList(){
        $list.empty();
        if (!dzFiles.length){ return; }
        dzFiles.forEach((f,idx)=>{
          const ext = getExt(f.name);
          $list.append(
            `<div class="file-item" data-idx="${idx}">
              <div class="file-icon"><i class="fa ${extIcon(ext)}"></i></div>
              <div class="file-meta">
                <div class="file-name" title="${f.name}">${f.name}</div>
                <div class="file-size">${bytesToSize(f.size)}</div>
              </div>
              <button type="button" class="file-remove" title="Remover" aria-label="Remover arquivo">
                <i class="fa fa-times"></i>
              </button>
            </div>`
          );
        });
      }
      function addFiles(fileList){
        // transforma em array e evita duplicados por nome+tamanho
        Array.from(fileList).forEach(file=>{
          const key = file.name + '|' + file.size;
          const exists = dzFiles.some(f => (f.name+'|'+f.size) === key);
          if (!exists){ dzFiles.push(file); }
        });
        renderList();
      }

      $browse.on('click', ()=> $input.trigger('click'));
      $input.on('change', function(){
        addFiles(this.files);
        // limpa o input para permitir selecionar o mesmo arquivo novamente, se removido
        this.value = '';
      });

      // drag events
      ['dragenter','dragover'].forEach(ev=>{
        $drop.on(ev, function(e){
          e.preventDefault(); e.stopPropagation();
          $(this).addClass('dragover');
        });
      });
      ['dragleave','drop'].forEach(ev=>{
        $drop.on(ev, function(e){
          e.preventDefault(); e.stopPropagation();
          if (ev==='dragleave') $(this).removeClass('dragover');
        });
      });
      $drop.on('drop', function(e){
        $(this).removeClass('dragover');
        const dt = e.originalEvent.dataTransfer;
        if (dt && dt.files && dt.files.length){ addFiles(dt.files); }
      });

      // remover arquivo da lista
      $list.on('click','.file-remove', function(){
        const idx = +$(this).closest('.file-item').data('idx');
        dzFiles.splice(idx, 1);
        renderList();
      });

      // enter/space no dropzone abre seletor
      $drop.on('keydown', function(e){
        if (e.key === 'Enter' || e.key === ' '){
          e.preventDefault();
          $input.trigger('click');
        }
      });

      // ======================== Validação de Data ========================
      const currentYear = new Date().getFullYear();
      function validateDate(input){
        const selectedDate = new Date($(input).val());
        if (selectedDate.getFullYear() > currentYear) {
          Swal.fire({ icon:'warning', title:'Data inválida', text:'O ano não pode ser maior que o ano atual.', confirmButtonText:'Ok' });
          $(input).val('');
        }
      }
      $('#data_ato').on('change', function(){
        if ($(this).val()) validateDate(this);
      });

      // ======================== Envio do Form ========================
      $('#ato-form').on('submit', function(e){
        e.preventDefault();

        // Checa partes
        if ($('#partes-envolvidas tr').length === 0){
          Swal.fire({ icon:'warning', title:'Atenção!', text:'Adicione pelo menos uma parte envolvida.', confirmButtonText:'OK' });
          return;
        }

        // Monta FormData (campos do form)
        const formData = new FormData();
        // Campos simples:
        ['atribuicao','categoria','data_ato','livro','folha','termo','protocolo','matricula','descricao'].forEach(id=>{
          formData.append(id, $('#'+id).val());
        });

        // Partes envolvidas:
        const partes = [];
        $('#partes-envolvidas tr').each(function(){
          const cpf = $(this).find('td').eq(0).text();
          const nome = $(this).find('td').eq(1).text();
          partes.push({ cpf, nome });
        });
        formData.append('partes_envolvidas', JSON.stringify(partes));

        // Anexos
        if (dzFiles.length){
          dzFiles.forEach(f=> formData.append('file-input[]', f));
        }

        $.ajax({
          url: 'save_ato.php',
          type: 'POST',
          data: formData,
          processData: false,
          contentType: false,
          success: function(response) {
            try{
              const result = JSON.parse(response);
              if (result.status === 'success') {
                Swal.fire({ icon:'success', title:'Sucesso!', text:'Dados salvos com sucesso!', confirmButtonText:'OK' })
                .then(()=> window.location.href = result.redirect);
              } else {
                Swal.fire({ icon:'error', title:'Erro!', text:(result.message||'Erro ao salvar os dados.'), confirmButtonText:'OK' });
              }
            }catch(err){
              Swal.fire({ icon:'error', title:'Erro!', text:'Erro ao salvar os dados.', confirmButtonText:'OK' });
            }
          },
          error: function() {
            Swal.fire({ icon:'error', title:'Erro!', text:'Erro ao salvar os dados.', confirmButtonText:'OK' });
          }
        });
      });

    });
  </script>
<?php include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
