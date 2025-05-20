<style>  
        :root {  
            --bg-primary: #ffffff;  
            --bg-secondary: #f8f9fa;  
            --bg-tertiary: #e9ecef;  
            --text-primary: #2c3e50;  
            --text-secondary: #6c757d;  
            --border-color: #dee2e6;  
            --accent-color: #2196F3;  
            --success-color: #28a745;  
        }  

        body {  
            font-family: 'Inter', sans-serif;  
            background-color: var(--bg-secondary);  
            color: var(--text-primary);  
            margin: 0;  
            padding: 0;  
        }  

        .container {  
            background-color: var(--bg-primary);  
            border-radius: 16px;  
            padding: 2rem;  
            margin-top: 20px;  
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);  
        }  

        .page-header {  
            display: flex;  
            align-items: center;  
            justify-content: space-between;  
            margin-bottom: 2rem;  
            padding-bottom: 1rem;  
            border-bottom: 2px solid var(--border-color);  
        }  

        .btn-primary {  
            background: linear-gradient(45deg, var(--accent-color), #1976D2);  
            color: white;  
            border: none;  
            padding: 12px 24px;  
            border-radius: 8px;  
            font-weight: 600;  
            transition: all 0.3s ease;  
        }  
        
        .btn-primary:hover {  
            transform: translateY(-2px);  
            box-shadow: 0 5px 15px rgba(33, 150, 243, 0.3);  
        }  

        .loading-overlay {  
            position: fixed;  
            top: 0;  
            left: 0;  
            right: 0;  
            bottom: 0;  
            background-color: rgba(0,0,0,0.7);  
            display: none;  
            align-items: center;  
            justify-content: center;  
            z-index: 9999;  
            flex-direction: column;  
        }  
        
        .progress-container {  
            width: 300px;  
            background-color: var(--bg-primary);  
            border-radius: 12px;  
            padding: 30px;  
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);  
        }  
        
        .progress-title {  
            text-align: center;  
            margin-bottom: 15px;  
            color: var(--text-primary);  
            font-weight: 600;  
        }  
        
        .progress-bar-container {  
            height: 8px;  
            background-color: var(--bg-tertiary);  
            border-radius: 4px;  
            margin-bottom: 10px;  
            overflow: hidden;  
        }  
        
        .progress-bar {  
            height: 100%;  
            background: linear-gradient(90deg, var(--accent-color), #1976D2);  
            border-radius: 4px;  
            width: 0%;  
            transition: width 0.3s ease;  
        }  
        
        .progress-text {  
            text-align: right;  
            color: var(--text-primary);  
            font-size: 14px;  
            font-weight: 600;  
        }  
        
        /* Custom Dropzone Styling */  
        .dropzone {  
            border: 2px dashed var(--border-color);  
            border-radius: 12px;  
            background: var(--bg-secondary);  
            min-height: 200px;  
            padding: 20px;  
            display: flex;  
            flex-direction: column;  
            align-items: center;  
            justify-content: center;  
            cursor: pointer;  
            transition: all 0.3s ease;  
            margin-bottom: 20px;  
        }  
        
        .dropzone:hover {  
            border-color: var(--accent-color);  
            background-color: rgba(33, 150, 243, 0.05);  
        }  
        
        .dropzone .dz-message {  
            text-align: center;  
        }  
        
        .dropzone .dz-message .text {  
            font-size: 18px;  
            font-weight: 500;  
            color: var(--text-primary);  
            margin-top: 15px;  
        }  
        
        .dropzone .dz-message .icon {  
            font-size: 48px;  
            color: var(--accent-color);  
        }  
        
        .dropzone .dz-preview {  
            margin: 10px;  
        }  
        
        .dropzone .dz-preview .dz-image {  
            border-radius: 8px;  
        }  
        
        .dropzone .dz-preview .dz-success-mark,  
        .dropzone .dz-preview .dz-error-mark {  
            margin-top: -25px;  
        }  
        
        .file-info {  
            font-size: 14px;  
            color: var(--text-secondary);  
            margin-top: 5px;  
        }  
    </style>