Dim args, fso, txtFile, docFile
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

Dim word
Set word = CreateObject("Word.Application")

On Error Resume Next
word.Visible = False
Set doc = word.Documents.Open(txtFile)
doc.SaveAs2 docFile, 16 ' 16 = wdFormatXMLDocument (DOCX)
doc.Close False
word.Quit
