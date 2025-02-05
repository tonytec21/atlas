Dim args, fso, txtFile, docFile, stream
Set args = WScript.Arguments

If args.Count < 2 Then
    WScript.Echo "Uso: word.vbs <arquivo_txt> <arquivo_docx>"
    WScript.Quit 1
End If

txtFile = args(0)
docFile = args(1)

Set fso = CreateObject("Scripting.FileSystemObject")

If Not fso.FileExists(txtFile) Then
    WScript.Echo "Arquivo .txt não encontrado: " & txtFile
    WScript.Quit 1
End If

Dim word, doc, para, txtContent
Set word = CreateObject("Word.Application")
On Error Resume Next
word.Visible = False

' Lê o arquivo de texto como UTF-8
Set stream = CreateObject("ADODB.Stream")
stream.Type = 2 ' Texto
stream.Mode = 3 ' Leitura e escrita
stream.Charset = "utf-8"
stream.Open
stream.LoadFromFile txtFile
txtContent = stream.ReadText
stream.Close

' Cria um novo documento no Word
Set doc = word.Documents.Add
doc.Content.Text = txtContent

' Define a fonte padrão como Arial, tamanho 12, alinhamento justificado
With doc.Content.Font
    .Name = "Arial"
    .Size = 12
End With
doc.Content.ParagraphFormat.Alignment = 3 ' Justificado

' Lista de trechos que devem ser formatados como negrito
Dim boldWords
boldWords = Array("IMÓVEL URBANO.", "PROPRIETÁRIO:", "ORIGEM:", "REGISTRO ANTERIOR:", "R.01 - Mat. XX.")

' Aplica o estilo negrito nos trechos específicos
Dim rng, i
For i = LBound(boldWords) To UBound(boldWords)
    Set rng = doc.Content
    With rng.Find
        .Text = boldWords(i)
        .Forward = True
        .MatchCase = True
        .Execute
    End With
    If rng.Find.Found Then
        rng.Font.Bold = True
    End If
Next

' Salva o documento como DOCX
doc.SaveAs2 docFile, 16 ' 16 = wdFormatXMLDocument (DOCX)
doc.Close False
word.Quit
