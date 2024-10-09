/**
 * Ajax handle for the auth form submission
 *
 * @package WordPilot
 * @since 1.0.0
 */

document.addEventListener(
	"DOMContentLoaded",
	function () {
		var form      = document.getElementById( "wordpilot-auth-form" );
		var resultDiv = document.getElementById( "comparison_result" );
		var spinner   = document.getElementById( "auth-spinner" );

		if (form) {
			form.addEventListener(
				"submit",
				function (e) {
					e.preventDefault();
					if (spinner) {
						spinner.style.display = "inline";
					}
					var formData = new FormData( form );
					formData.append( "action", "wordpilot_verify_key" );
					formData.append( "nonce", wordpilotData.nonce ); // '<?php echo esc_js(wp_create_nonce('wordpilot_verify_key')); ?>');

					fetch(
						wordpilotData.ajax_url,
						{
							method: "POST",
							body: formData,
							credentials: "same-origin",
						}
					)
					.then(
						function (response) {
							if ( ! response.ok) {
								throw new Error( "Network response was not ok" );
							}
							return response.json();
						}
					)
					.then(
						function (data) {
							if (resultDiv) {
								resultDiv.classList.remove( "notice-success", "notice-error" );
								resultDiv.classList.add(
									data.success ? "notice-success" : "notice-error"
								);
								var messageParagraph = resultDiv.querySelector( "p" );
								if (messageParagraph) {
											messageParagraph.textContent = data.data.message;
								}
								resultDiv.style.display = "block";
							}
							if (data.success) {
								setTimeout(
									function () {
										window.location.href = wordpilotData.dashboard_url;
									},
									4000
								);
							}
						}
					)
					.catch(
						function (error) {
							if (resultDiv) {
								resultDiv.classList.remove( "notice-success", "notice-error" );
								resultDiv.classList.add( "notice-error" );
								var messageParagraph = resultDiv.querySelector( "p" );
								if (messageParagraph) {
									messageParagraph.textContent =
									"An error occurred: " + error.message;
								}
								resultDiv.style.display = "block";
							}
							console.error( "Error:", error );
						}
					)
					.finally(
						function () {
							if (spinner) {
								spinner.style.display = "none";
							}
						}
					);
				}
			);
		}
	}
);
