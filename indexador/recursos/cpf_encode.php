<?php

/**
 * Ao tentar validar um arquivo XML, se algum erro
 * for encontrado a libxml irá gerar Warnings, o que
 * não creio que seja o mais interessante para nós.
 * Para evitar que isso aconteça, você pode determinar
 * que irá obter os erros por sua própria conta. Lembre-se
 * que esta função abaixo deve ser chamada antes de
 * instanciar qualquer objeto da classe DomDocument!
 * 
 * 
 * $binStr = '';
*foreach ($fileBytes as $fileByte) 
*{
*  $binStr .= pack('i', $fileByte);          
*}
*$binStr .= "\n";
*$fileTemp = '/tmp/'.$filename;
*file_put_contents($fileTemp, $binStr);
 */

function libxml_display_error($error)
{
    $return = "<br/>\n";
    switch ($error->level) {
        case LIBXML_ERR_WARNING:
            $return .= "<b>Warning $error->code</b>: ";
            break;
        case LIBXML_ERR_ERROR:
            $return .= "<b>Error $error->code</b>: ";
            break;
        case LIBXML_ERR_FATAL:
            $return .= "<b>Fatal Error $error->code</b>: ";
            break;
    }
    $return .= trim($error->message);
    if ($error->file) {
        $return .=    " in <b>$error->file</b>";
    }
    $return .= " on line <b>$error->line</b>\n";

    return $return;
}

function libxml_display_errors() {
    $errors = libxml_get_errors();
    foreach ($errors as $error) {
        print libxml_display_error($error);
    }
    libxml_clear_errors();
}

// Enable user error handling
libxml_use_internal_errors(true);

$xml = new DOMDocument();
$xml->load('./sign/CPF_TESTE_ASSINADO.xml');

if (!$xml->schemaValidate('https://wsh.registrocivil.org.br/inscricaoCPF.xsd')) {
    print '<b>DOMDocument::schemaValidate() Generated Errors!</b>';
    libxml_display_errors();
}else{
    echo 'sem erro';
}







?>