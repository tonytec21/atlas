#!/usr/bin/env python3  
"""  
PDF Splitter - Divide as páginas de um PDF em duas metades  
"""  
import os  
import sys  
import json  
import time  
import zipfile  
import traceback  
from pathlib import Path  
from io import BytesIO  
import multiprocessing  
from PIL import Image  
from pypdf import PdfReader, PdfWriter  
from reportlab.pdfgen import canvas  

# Configuração para Poppler no Windows  
POPPLER_PATH = r"C:\Program Files\poppler-24.08.0\bin"  
if not os.path.exists(POPPLER_PATH):  
    POPPLER_PATH = r"C:\Program Files\poppler-24.08.0\Library\bin"  

def update_progress(progress_file, current=0, total=0, percentage=0, status="processing", message=""):  
    """Atualiza o arquivo de progresso JSON"""  
    progress = {  
        "current": current,  
        "total": total,  
        "percentage": percentage,  
        "status": status,  
        "message": message  
    }  
    
    with open(progress_file, 'w', encoding='utf-8') as f:  
        json.dump(progress, f)  

def create_temp_dir(base_dir):  
    """Cria um diretório temporário para os arquivos de saída"""  
    temp_dir = os.path.join(base_dir, f"temp_{int(time.time())}")  
    os.makedirs(temp_dir, exist_ok=True)  
    return temp_dir  

def convert_pdf_to_images(pdf_path, dpi=200):  
    """Converte um PDF em uma lista de imagens"""  
    # Importamos pdf2image aqui para configurar o poppler_path  
    from pdf2image import convert_from_path  
    
    try:  
        # Tentar converter com o Poppler configurado  
        if os.name == 'nt' and os.path.exists(POPPLER_PATH):  # Windows e Poppler existe  
            images = convert_from_path(pdf_path, dpi=dpi, poppler_path=POPPLER_PATH)  
        else:  
            images = convert_from_path(pdf_path, dpi=dpi)  
        return images  
    except Exception as e:  
        print(f"Erro ao converter PDF para imagem: {str(e)}")  
        traceback.print_exc()  
        raise  

def trim_white_borders(img):  
    """Remove bordas brancas de uma imagem"""  
    # Converter para escala de cinza para simplificar detecção de bordas  
    if img.mode != 'L':  
        img_gray = img.convert('L')  
    else:  
        img_gray = img  
    
    # Encontrar os limites não-brancos  
    bg = Image.new('L', img_gray.size, 255)  
    diff = Image.new('L', img_gray.size)  
    threshold = 245  # Valor para considerar como branco  
    
    for x in range(img_gray.size[0]):  
        for y in range(img_gray.size[1]):  
            pixel = img_gray.getpixel((x, y))  
            if pixel < threshold:  # Não é branco  
                diff.putpixel((x, y), 0)  # Marcar como não-branco  
    
    # Encontrar a bounding box  
    bbox = diff.getbbox()  
    if bbox:  
        return img.crop(bbox)  # Cortar a imagem original  
    return img  # Retornar imagem original se não encontrar áreas não-brancas  

def image_to_pdf(img, output_path):  
    """Converte uma imagem para PDF"""  
    # Salvar imagem em um buffer de memória  
    img_buffer = BytesIO()  
    img.save(img_buffer, format='PNG')  
    img_buffer.seek(0)  
    
    # Criar um novo PDF com o tamanho exato da imagem  
    c = canvas.Canvas(output_path, pagesize=(img.width, img.height))  
    c.drawImage(img_buffer, 0, 0, width=img.width, height=img.height)  
    c.save()  

def process_page(args):  
    """Processa uma página e retorna os caminhos dos arquivos gerados"""  
    try:  
        page_num, img, output_dir, dpi = args  
        
        # Divisão da imagem em duas metades  
        width, height = img.size  
        left_half = img.crop((0, 0, width // 2, height))  
        right_half = img.crop((width // 2, 0, width, height))  
        
        # Remover bordas brancas  
        left_half_trimmed = trim_white_borders(left_half)  
        right_half_trimmed = trim_white_borders(right_half)  
        
        # Gerar nomes de arquivos  
        left_pdf = os.path.join(output_dir, f"page_{page_num+1}_left.pdf")  
        right_pdf = os.path.join(output_dir, f"page_{page_num+1}_right.pdf")  
        
        # Converter para PDF  
        image_to_pdf(left_half_trimmed, left_pdf)  
        image_to_pdf(right_half_trimmed, right_pdf)  
        
        return page_num + 1, [left_pdf, right_pdf]  
    except Exception as e:  
        print(f"Erro ao processar página {page_num+1}: {str(e)}")  
        traceback.print_exc()  
        return page_num + 1, []  

def main():  
    """Função principal"""  
    # Verificar argumentos  
    if len(sys.argv) < 4:  
        print("Uso: python pdf_splitter.py <entrada.pdf> <saida.zip> <progresso.json> [dpi=200] [num_processes=auto]")  
        sys.exit(1)  
    
    # Parâmetros de entrada  
    input_pdf = sys.argv[1]  
    output_zip = sys.argv[2]  
    progress_file = sys.argv[3]  
    dpi = int(sys.argv[4]) if len(sys.argv) > 4 else 200  
    num_processes_arg = sys.argv[5] if len(sys.argv) > 5 else "auto"  
    
    # Criar diretório temporário  
    output_dir = create_temp_dir(os.path.dirname(output_zip))  
    
    try:  
        # Inicializar progresso  
        update_progress(progress_file, status="initializing",   
                       message="Inicializando processamento...")  
        
        # Verificar se o arquivo existe  
        if not os.path.exists(input_pdf):  
            raise FileNotFoundError(f"Arquivo de entrada não encontrado: {input_pdf}")  
        
        # Obter informações do PDF  
        update_progress(progress_file, status="analyzing",   
                       message="Analisando arquivo PDF...")  
        
        with open(input_pdf, 'rb') as f:  
            pdf = PdfReader(f)  
            total_pages = len(pdf.pages)  
        
        # Converter PDF para imagens  
        update_progress(progress_file, total=total_pages, status="converting",   
                       message=f"Convertendo {total_pages} páginas para imagens...")  
        
        images = convert_pdf_to_images(input_pdf, dpi)  
        
        # Configurar processamento paralelo  
        if num_processes_arg == "auto":  
            num_processes = max(1, multiprocessing.cpu_count() - 1)  
        else:  
            num_processes = int(num_processes_arg)  
        
        # Limitar o número de processos para PDFs pequenos  
        num_processes = min(num_processes, total_pages)  
        
        # Preparar argumentos para processamento  
        page_args = [(i, img, output_dir, dpi) for i, img in enumerate(images)]  
        
        # Iniciar processamento  
        update_progress(progress_file, current=0, total=total_pages, percentage=0,   
                       message=f"Processando páginas com {num_processes} processo(s)...")  
        
        # Processar as páginas  
        processed_pages = 0  
        output_files = []  
        
        # Processamento sequencial ou paralelo  
        if total_pages == 1 or num_processes == 1:  
            # Processamento sequencial  
            for args in page_args:  
                page_num, files = process_page(args)  
                output_files.extend(files)  
                processed_pages += 1  
                percentage = int((processed_pages / total_pages) * 100)  
                update_progress(progress_file, current=processed_pages, total=total_pages,   
                               percentage=percentage,   
                               message=f"Processando página {page_num} de {total_pages}")  
        else:  
            # Processamento paralelo  
            with multiprocessing.Pool(processes=num_processes) as pool:  
                for page_num, files in pool.imap_unordered(process_page, page_args):  
                    output_files.extend(files)  
                    processed_pages += 1  
                    percentage = int((processed_pages / total_pages) * 100)  
                    update_progress(progress_file, current=processed_pages, total=total_pages,   
                                   percentage=percentage,   
                                   message=f"Processando página {page_num} de {total_pages}")  
        
        # Criar arquivo ZIP  
        update_progress(progress_file, percentage=95,   
                       message="Criando arquivo ZIP com os resultados...")  
        
        with zipfile.ZipFile(output_zip, 'w', zipfile.ZIP_DEFLATED) as zipf:  
            for file_path in output_files:  
                zipf.write(file_path, os.path.basename(file_path))  
        
        # Limpar arquivos temporários  
        update_progress(progress_file, percentage=99,   
                       message="Finalizando e limpando arquivos temporários...")  
        
        for file_path in output_files:  
            try:  
                os.remove(file_path)  
            except:  
                pass  
        
        try:  
            os.rmdir(output_dir)  
        except:  
            pass  
        
        # Concluído com sucesso  
        update_progress(progress_file, current=total_pages, total=total_pages,   
                       percentage=100, status="completed",   
                       message="Processamento concluído com sucesso!",  
                       download_url=f"download.php?file={os.path.basename(output_zip)}")  
        
    except Exception as e:  
        error_message = f"Erro durante o processamento: {str(e)}"  
        print(error_message)  
        traceback.print_exc()  
        update_progress(progress_file, status="error", message=error_message)  
        sys.exit(1)  

if __name__ == "__main__":  
    main()