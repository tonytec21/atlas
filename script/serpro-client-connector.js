(function () {
	console.debug('serpro-client-connector.js');

	checkEnvironment();
	initApp();
	initSerproSignerClient();

	function initSerproSignerClient() {
		var timeoutDefault = 3000;
		var tryAgainTimeoutWebSocket;
		var tryAgainTimeoutVerify;

		// Configure SerproSigner
		configureDesktopClient();

		// Verify if is installed AND running
		verifyDesktopClientInstallation();

		// connect DIRECT to WebSocket
		// connectToWebSocket();

		function configureDesktopClient() {
			window.SerproSignerClient.setDebug(true);
			window.SerproSignerClient.setUriServer("wss", "127.0.0.1", 65156, "/signer");
		}

		function verifyDesktopClientInstallation() {
			window.SerproSignerClient.verifyIsInstalledAndRunning()
				.success(function (response) {
					clearInterval(tryAgainTimeoutVerify);
					connectToWebSocket();
				}).error(function (response) {
					showStatusOff();
					// Try again in Xms
					clearInterval(tryAgainTimeoutVerify);
					tryAgainTimeoutVerify = setTimeout(verifyDesktopClientInstallation, timeoutDefault);
				});
		}

		function connectToWebSocket() {
			window.SerproSignerClient.connect(callbackOpenClose, callbackOpenClose, callbackError);
		}

		function callbackOpenClose(connectionStatus) {

			if (connectionStatus === 1) {
				console.debug('Connected on Server');
				showStatusOn();

				clearInterval(tryAgainTimeoutWebSocket);
			} else {
				console.debug('Warn user to download/execute Agent-Desktop AND try again in ' + timeoutDefault + 'ms');
				showStatusOff();

				// Try again in Xms
				clearInterval(tryAgainTimeoutWebSocket);
				tryAgainTimeoutWebSocket = setTimeout(verifyDesktopClientInstallation, timeoutDefault);
			}
		}

		function callbackError(event) {
			var serverAuthorizarion = $('.js-server-authorization');
			serverAuthorizarion.show();

			if (event.error !== undefined) {
				if (event.error !== null && event.error !== 'null') {
					console.error({ message: event.error });
				} else {
					console.error({ message: 'Unknown error' });
				}
			}
		}

		function showStatusOn() {
			var serverStatus = $('.js-server-status');
			serverStatus.hide();
			serverStatus.filter('.js-server-status-on').show();
			var serverAuthorizarion = $('.js-server-authorization');
			serverAuthorizarion.hide();
		}

		function showStatusOff() {
			var serverStatus = $('.js-server-status');
			serverStatus.hide();
			serverStatus.filter('.js-server-status-off').show();
		}
	}

	function sign(params) {
		// Valida os parâmetros obrigatórios
		if (!params.type) {
			throw new Error('Sign type is not defined.');
		}
		if (!params.data && params.type !== 'file') {
			throw new Error('Sign data is not defined.');
		}

		// Antes de assinar
		params.beforeSign && params.beforeSign();

		// Sign - Chama o assinador
		window.SerproSignerClient.sign(params.type, params.data, params.textEncoding, params.outputDataType, params.attached)
			.success(function (response) {
				if (response.actionCanceled) {
					console.debug('Action canceled by User.');
					params.onCancel && params.onCancel(response);
				} else {
					console.debug('Sucesso:', response);
					var resultado = $('#assinatura');
					resultado.val(response.signature);
					//resultado.autogrow();

					// Para o caso de assinatura de arquivo, exibe o hash
					var hash = $('#file-base64');
					//if (hash && params.type != 'pdf') {
					  hash.val(response.original);
					//}

					// Para o caso de assinatura de arquivo, exibe o nome do arquivo
					var fileName = $('#filename-value');
					if (fileName) {
					  fileName.val(response.originalFileName);
					}

					console.log('response');
					console.log(response);

					if (params.type == 'file') {
						params.onSuccess && params.onSuccess({
							original: {
								size: response.original.length,
								base64: response.original
							},
							signature: {
								size: response.signature.length,
								base64: response.signature
							},
							fileName: response.originalFileName
						});
					} else {
						params.onSuccess && params.onSuccess({
							original: {
								size: response.original.length,
								base64: response.original
							},
							signature: {
								size: response.signature.length,
								base64: response.signature
							}
						});
					}
				}
				params.afterSign && params.afterSign(response);
			})
			.error(function (error) {
				console.debug('Error:', error);
				params.onError && params.onError(error);
				params.afterSign && params.afterSign(error);
			});
	}

	function verify(params) {
		// Verify - Chama o assinador
		window.SerproSignerClient.verify(params.type, params.inputData, params.inputSignature, null, params.algorithmOIDHash)
			.success(function (response) {
				if (response.actionCanceled) {
					console.debug('Action canceled by User.');
					params.onCancel && params.onCancel(response);
				} else {
					console.debug('Verify:', response);
					console.debug(response.signerSignatureValidations[0]);

					var dados = response.signerSignatureValidations[0];
					console.log('DADOS', dados)
					var cadeias = "";
					$.each(dados.cadeiaCertificado, function(i, v) {
					  cadeias += (v + "\n");
					});
					$('#ass_assinante').val(dados.assinante);
					$('#ass_cadeia').val(cadeias);
					$('#ass_data').val(dados.signDate);
					$('#ass_politica').val(dados.signaturePolicy);

					params.onSuccess && params.onSuccess({
					});
				}
				params.afterSign && params.afterSign(response);
			})
			.error(function (error) {
				$('#ass_assinante').val('erro');
				$('#ass_cadeia').val('erro');
				$('#ass_data').val('erro');
				$('#ass_politica').val('erro');
        if (error.error) {
				  alert(error.error);
				}
				console.debug('Error:', error);
				params.onError && params.onError(error);
				params.afterSign && params.afterSign(error);
			});
	}

	function attached(params) {
		// Attached - Chama o assinador
		window.SerproSignerClient.attached(params.inputSignature)
			.success(function (response) {
				if (response.actionCanceled) {
					console.debug('Action canceled by User.');
					params.onCancel && params.onCancel(response);
				} else {
					console.debug('Attached:', response);
					console.debug(response);

					$('#conteudo_base64').val(response.attachedContent);
					$('#conteudo_atob').val(atob(response.attachedContent));

					params.onSuccess && params.onSuccess({
					});
				}
				params.afterSign && params.afterSign(response);
			})
			.error(function (error) {
				console.debug('Error:', error);
				params.onError && params.onError(error);
				params.afterSign && params.afterSign(error);
			});
	}

	function initApp() {
    // hide authorization link
    var serverAuthorizarion = $('.js-server-authorization');
    serverAuthorizarion.hide();
		// handle forms
		$('form#assinarHash').submit(signHash);
		$('form#assinarTexto').submit(signText);
    $('form#assinarArquivo').submit(signFile);
    $('form#assinarPdf').submit(signPdf);

		$('#validar-assinatura-hash').click(validateHashSign);
		$('#validar-assinatura-texto').click(validateTextSign);
        $('#validar-assinatura-arquivo').click(validateFileSign);
        $('#validarAssinaturaPdf').click(validatePdfSign);

		$('#extrairConteudo').click(extractContents);

		// ---------- Sign HASH ----------
		function signHash(event) {
			event.preventDefault();

			var hashData = $('#hash-value').val();

			sign({
				type: 'hash',
				data: hashData,
				onSuccess: onSuccessHashHandler,
				onError: function (error) { console.debug('ERRO: ', error) }, // optional
				// onCancel: onCancelHandler, // optional
				// beforeSign: beforeSignHandler, // optional
				// afterSign: afterSignHandler // optional
			});

			function onSuccessHashHandler(data) {
				console.debug('HASH ON SUCCESS: ', data);
			}
		}

		// ---------- Sign TEXT ----------
		function signText(event) {
			event.preventDefault();

			var textData = $("#texto").val();

			sign({
				type: 'text',
				data: textData,
				textEncoding: 'UTF-8',
        attached: $('#incluir-coteudo').prop('checked'),
        outputDataType: 'base64',
				onSuccess: onSuccessTextHandler,
				onError: function (error) { console.debug('ERRO: ', error) }, // optional
				// onCancel: onCancelHandler, // optional
				// beforeSign: beforeSignHandler, // optional
				// afterSign: afterSignHandler // optional
			});

			function onSuccessTextHandler(data) {
				console.debug('TEXT ON SUCCESS: ', data);
			}
		}

		// ---------- Sign FILE ----------
		function signFile(event) {
			event.preventDefault();

			sign({
				type: 'file',
				data: null,
				attached: $('#incluir-coteudo').prop('checked'),
				onSuccess: onSuccessFileHandler,
				onError: function (error) { console.debug('ERRO: ', error) }, // optional
				// onCancel: onCancelHandler, // optional
				// beforeSign: beforeSignHandler, // optional
				// afterSign: afterSignHandler // optional
			});

			function onSuccessFileHandler(data) {
				console.debug('FILE ON SUCCESS: ', data);
			}
		}

    // ---------- Sign PDF ----------
		function signPdf(event) {
			event.preventDefault();

			sign({
				type: 'pdf',
				data: $('#content-value').val(),
				onSuccess: onSuccessPdfHandler,
				onError: function (error) { console.debug('ERRO: ', error) }, // optional
				// onCancel: onCancelHandler, // optional
				// beforeSign: beforeSignHandler, // optional
				// afterSign: afterSignHandler // optional
			});

			function onSuccessPdfHandler(data) {
				console.debug('PDF ON SUCCESS: ', data);
			}
		}

		// ---------- Validate Hash Signature ----------
		function validateHashSign(event) {
			event.preventDefault();

			var hashData = $('#hash-value').val();
			var signature = $('#assinatura').val();
			var algorithm = $('#hash-algorithm').val();
			algorithm = algorithm==='SHA-256' ? '2.16.840.1.101.3.4.2.1' : '2.16.840.1.101.3.4.2.3';
			console.log('ALGORITHM', algorithm)

			verify({
				type: 'hash',
				inputData: hashData,
				inputSignature: signature,
				algorithmOIDHash: algorithm,
				onSuccess: onSuccessVerifyHandler,
				onError: function (error) { console.debug('ERRO: ', error) }, // optional
				// onCancel: onCancelHandler, // optional
				// beforeSign: beforeSignHandler, // optional
				// afterSign: afterSignHandler // optional
			});

			function onSuccessVerifyHandler(data) {
				console.debug("VALIDAR ASSINATURA");
				console.debug(data);
			}
		}

		// ---------- Validate Text Signature ----------
		function validateTextSign(event) {
			event.preventDefault();

			var data = $('#texto').val();
			var signature = $('#assinatura').val();

			verify({
				type: 'text',
				inputData: data,
				inputSignature: signature,
				onSuccess: onSuccessVerifyHandler,
				onError: function (error) { console.debug('ERRO: ', error) }, // optional
				// onCancel: onCancelHandler, // optional
				// beforeSign: beforeSignHandler, // optional
				// afterSign: afterSignHandler // optional
			});

			function onSuccessVerifyHandler(data) {
				console.debug("VALIDAR ASSINATURA");
				console.debug(data);
			}
		}

		// ---------- Validate File Signature ----------
		function validateFileSign(event) {
			event.preventDefault();

			var data = $('#file-base64').val();
			var signature = $('#assinatura').val();

			verify({
				type: 'file',
				inputData: data,
				inputSignature: signature,

				onSuccess: onSuccessVerifyHandler,
				onError: function (error) { console.debug('ERRO: ', error) }, // optional
				// onCancel: onCancelHandler, // optional
				// beforeSign: beforeSignHandler, // optional
				// afterSign: afterSignHandler // optional
			});

			function onSuccessVerifyHandler(data) {
				console.debug("VALIDAR ASSINATURA");
				console.debug(data);
			}
		}


		// ---------- Validate PDF Signature ----------
		function validatePdfSign(event) {
			event.preventDefault();

			var signedPdf = $('#assinatura').val();

			verify({
				type: 'pdf',
				inputData: signedPdf,

				onSuccess: onSuccessVerifyHandler,
				onError: function (error) { console.debug('ERRO: ', error) }, // optional
				// onCancel: onCancelHandler, // optional
				// beforeSign: beforeSignHandler, // optional
				// afterSign: afterSignHandler // optional
			});

			function onSuccessVerifyHandler(data) {
				console.debug("VALIDAR ASSINATURA PDF");
				console.debug(data);
			}
		}


		// ---------- Extract Contents ----------
		function extractContents(event) {
			event.preventDefault();

			var signature = $('#assinatura').val();

			attached({
				inputSignature: signature,
				onSuccess: onSuccessVerifyHandler,
				onError: function (error) { console.debug('ERRO: ', error) }, // optional
				// onCancel: onCancelHandler, // optional
				// beforeSign: beforeSignHandler, // optional
				// afterSign: afterSignHandler // optional
			});

			function onSuccessVerifyHandler(data) {
				console.debug("CONTEÚDO");
				console.debug(data);
			}
		}

	}

	function checkEnvironment() {
		var env = {};
		// Browser
		env.ie = is.ie();
		env.edge = is.edge();
		env.chrome = is.chrome();
		env.firefox = is.firefox();
		env.opera = is.opera();
		env.safari = is.safari();

		// OS
		env.windows = is.windows();
		env.mac = is.mac();
		env.linux = is.linux();

		// Type
		env.desktop = is.desktop();
		env.mbile = is.mobile();
		env.blackberry = is.blackberry();

		// hide all
		$('.js-is-system > *').hide();
		$('.js-is-browser > *').hide();
		for (var key in env) {
			var value = env[key];
			if (value === true) {
				$('.js-is-' + key).show();
			}
		}
	}

})();
