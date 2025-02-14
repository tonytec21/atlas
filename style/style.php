<style>  
        body.dark-mode .form-control[readonly] {
            background-color: #474747;
        }

        .page-title {
            justify-content: space-around!important; 
            margin-bottom: 30px!important;
            font-size: 2.0rem!important;
        }

        :root {  
            /* Light Theme */  
            --bg-primary: #ffffff;  
            --bg-secondary: #f8f9fa;  
            --bg-tertiary: #e9ecef;  
            --text-primary: #2c3e50;  
            --text-secondary: #6c757d;  
            --border-color: #dee2e6;  
            --input-bg: #ffffff;  
            --input-border: #ced4da;  
            --card-shadow: 0 2px 15px rgba(0,0,0,0.08);  
            --hover-bg: #f8f9fa;  
            --accent-color: #2196F3;  
            --accent-hover: #1976D2;  
            --danger-color: #dc3545;  
            --success-color: #28a745;  
        }  

        body.dark-mode {  
            --bg-primary: #1a1d21;  
            --bg-secondary: #242832;  
            --bg-tertiary: #2d3238;  
            --text-primary: #e9ecef;  
            --text-secondary: #adb5bd;  
            --border-color: #2d3238;  
            --input-bg: #2d3238;  
            --input-border: #404650;  
            --card-shadow: 0 2px 15px rgba(0,0,0,0.2);  
            --hover-bg: #2d3238;  
            --accent-color: #3498db;  
            --accent-hover: #2980b9;  
        }  

        body {  
            font-family: 'Inter', sans-serif;  
            background-color: var(--bg-secondary);  
            color: var(--text-primary);  
            transition: all 0.3s ease;  
            margin: 0;  
            padding: 0;  
        }  

        .main-content {  
            padding: 2rem 1rem;  
        }  

        .container {  
            background-color: var(--bg-primary);  
            border-radius: 16px;  
            padding: 2rem;  
            box-shadow: var(--card-shadow);  
            margin-top: 20px;  
        }  

        .page-header {  
            display: flex;  
            align-items: center;  
            justify-content: space-between;  
            margin-bottom: 2rem;  
            padding-bottom: 1rem;  
            border-bottom: 2px solid var(--border-color);  
        }  

        .page-title {  
            font-size: 1.5rem;  
            font-weight: 600;  
            color: var(--text-primary);  
            margin: 0;  
            display: flex;  
            align-items: center;  
            gap: 0.5rem;  
        }  

        .page-title i {  
            color: var(--accent-color);  
            font-size: 1.75rem;  
        }  

        .filter-section {  
            background-color: var(--bg-secondary);  
            border-radius: 12px;  
            padding: 1.5rem;  
            margin-bottom: 2rem;  
        }  

        .form-control {  
            background-color: var(--input-bg);  
            border: 1.5px solid var(--input-border);  
            color: var(--text-primary);  
            border-radius: 8px;  
            padding: 0.625rem 1rem;  
            transition: all 0.2s ease;  
            height: auto;  
        }  

        .form-control:focus {  
            border-color: var(--accent-color);  
            box-shadow: 0 0 0 0.2rem rgba(33, 150, 243, 0.15);  
            background-color: var(--input-bg);  
            color: var(--text-primary);  
        }  

        .form-label {  
            color: var(--text-secondary);  
            font-weight: 500;  
            font-size: 0.875rem;  
            margin-bottom: 0.5rem;  
        }  

        .btn {  
            padding: 0.625rem 1.25rem;  
            border-radius: 8px;  
            font-weight: 500;  
            display: inline-flex;  
            align-items: center;  
            justify-content: center;  
            gap: 0.5rem;  
            transition: all 0.3s ease;  
            border: none;  
        }  

        .btn-primary {  
            background: linear-gradient(45deg, var(--accent-color), var(--accent-hover));  
            color: white;  
        }  

        .btn-success {  
            background: linear-gradient(45deg, var(--success-color), #218838);  
            color: white;  
        }  

        .btn:hover {  
            transform: translateY(-1px);  
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);  
        }  

        .table-container {  
            background-color: var(--bg-primary);  
            border-radius: 12px;  
            overflow: hidden;  
            margin-top: 2rem;  
        }  

        .table {  
            margin-bottom: 0;  
            /* color: var(--text-primary);   */
            color: #000;  
            border-collapse: separate;  
            border-spacing: 0;  
            width: 100%;  
        }  

        .table th {  
            background-color: var(--bg-secondary);  
            font-weight: 600;  
            padding: 1rem;  
            font-size: 0.875rem;  
            text-transform: uppercase;  
            letter-spacing: 0.5px;  
            border-top: none;  
        }  

        .table td {  
            padding: 1rem;  
            vertical-align: middle;  
            border-top: 1px solid var(--border-color);  
        }  

        .table tbody tr:hover {  
            background-color: var(--hover-bg);  
        }  

        /* Custom Checkbox */  
        .custom-checkbox {  
            width: 18px;  
            height: 18px;  
            border: 2px solid var(--input-border);  
            border-radius: 4px;  
            position: relative;  
            cursor: pointer;  
            transition: all 0.2s ease;  
        }  

        .custom-checkbox:checked {  
            background-color: var(--accent-color);  
            border-color: var(--accent-color);  
        }  

        .custom-checkbox:checked::after {  
            content: '✓';  
            color: white;  
            position: absolute;  
            top: 50%;  
            left: 50%;  
            transform: translate(-50%, -50%);  
            font-size: 12px;  
        }  

        /* DataTables Customization */  
        .dataTables_wrapper .dataTables_length,  
        .dataTables_wrapper .dataTables_filter,  
        .dataTables_wrapper .dataTables_info,  
        .dataTables_wrapper .dataTables_processing,  
        .dataTables_wrapper .dataTables_paginate {  
            color: var(--text-secondary);  
            margin: 1rem;  
        }  

        .dataTables_wrapper .dataTables_filter input {  
            background-color: var(--input-bg);  
            border: 1.5px solid var(--input-border);  
            color: var(--text-primary);  
            border-radius: 8px;  
            padding: 0.4rem 1rem;  
            margin-left: 0.5rem;  
        }  

        .dataTables_wrapper .dataTables_paginate .paginate_button {  
            border-radius: 6px;  
            padding: 0.5rem 1rem;  
            margin: 0 2px;  
            border: none;  
            background: var(--bg-secondary);  
            color: var(--text-primary) !important;  
        }  

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {  
            background: var(--accent-color);  
            color: white !important;  
        }  

        /* Responsive Design */  
        @media (max-width: 768px) {  
            .container {  
                padding: 1rem;  
            }  

            .page-header {  
                flex-direction: column;  
                align-items: flex-start;  
                gap: 1rem;  
            }  

            .btn {  
                width: 100%;  
                margin: 0.5rem 0;  
            }  

            .table-responsive {  
                margin: 0 -1rem;  
                padding: 0 1rem;  
            }  
        }  

        .loading-spinner {  
            width: 50px;  
            height: 50px;  
            border: 5px solid var(--bg-primary);  
            border-top: 5px solid var(--accent-color);  
            border-radius: 50%;  
            animation: spin 1s linear infinite;  
        }  

        @keyframes spin {  
            0% { transform: rotate(0deg); }  
            100% { transform: rotate(360deg); }  
        }  

        .table th {  
        vertical-align: middle !important;
    }  

    .checkbox-column {  
        width: 40px;  
        text-align: center;  
        vertical-align: middle !important;  
    }  

    .form-check {  
        margin: 0;  
        padding: 0;  
        display: flex;  
        justify-content: center;  
        align-items: center;  
        min-height: auto;  
    }  

    .form-check-input {  
        margin: 0;  
        position: relative;  
    }  
    </style>