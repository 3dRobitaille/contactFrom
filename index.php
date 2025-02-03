<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, X-Requested-With");

$method = $_SERVER["REQUEST_METHOD"];

if ($method != "POST") {
	$response = array(
		'success' => false,
		'error' => true,
		'message' => 'GET method not supported',
		'data' => NULL
	);
	echo json_encode($response);
	exit;
}

if (!isset($_POST['shop']) || (isset($_POST['shop']) && empty($_POST['shop']))) {
	$response = array(
		'success' => false,
		'error' => true,
		'message' => 'Shop is required.',
		'data' => NULL
	);
	echo json_encode($response);
	exit;
}

if (!isset($_FILES['file'])) {
	$response = array(
		'success' => false,
		'error' => true,
		'message' => 'File is required.',
		'data' => NULL
	);
	echo json_encode($response);
	exit;
}

$shop = $_POST['shop'];
$accessToken = "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";
$file = $_FILES['file'];

$graphqlUrl = "https://$shop/admin/api/2024-07/graphql.json";

const STAGE_UPLOAD_CREATE_MUTATION = <<<'QUERY'
		mutation StagedUploadsCreate($input: [StagedUploadInput!]!) {
			stagedUploadsCreate(input: $input) {
				stagedTargets {
					url
					resourceUrl
					parameters {
						name
						value
					}
				}
				userErrors {
					field
					message
				}
			}
		}
	QUERY;

const FILE_CREATE_MUTATION = <<<'QUERY'
		mutation fileCreate($files: [FileCreateInput!]!) {
			fileCreate(files: $files) {
				files {
					id
					fileStatus
					alt
					fileErrors {
						message
						details
						code
					}
				}
				userErrors {
					field
					message
				}
			}
		}
	QUERY;

const GET_FILE_BY_ID = <<<'QUERY'
		query getFile($id: ID!) {
			node(id: $id) {
				id
				... on MediaImage {
					image {
						url
					}
				}
				... on GenericFile {
					url
				}
			}
		}
	QUERY;

function createStageUpload($graphqlUrl, $accessToken, $file) {
	$variables = [
		'input' => [
			'resource' => 'FILE',
			'filename' => $file['name'],
			'mimeType' => $file['type']
		]
	];

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $graphqlUrl);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['query' => STAGE_UPLOAD_CREATE_MUTATION, 'variables' => $variables]));
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		'Content-Type: application/json',
		'X-Shopify-Access-Token: ' . $accessToken
	]);

	// Execute the cURL request and get the response
	$response = curl_exec($ch);

	if (curl_errno($ch)) {
		//echo 'Error:' . curl_error($ch);
		return false;
	} else {
		// Decode and display the response
		$responseData = json_decode($response, true);
		return $responseData['data']['stagedUploadsCreate']['stagedTargets'][0];
	}

	// Close cURL connection
	curl_close($ch);
}

function uploadStageFile($file, $uploadUrl) {
	$filePath = $file['tmp_name']; 
    $fileName = $file['name']; 
    $mimeType = mime_content_type($filePath); 
	
	$fileData = file_get_contents($filePath);

	$ch = curl_init($uploadUrl);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
	curl_setopt($ch, CURLOPT_POSTFIELDS, $fileData);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		"Content-Type: {$mimeType}"
	]);

	$response = curl_exec($ch);
	$uploadHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	
	if ($uploadHttpCode < 200 || $uploadHttpCode >= 300) {
		//echo curl_error($ch);
		return false;
	}

	if (curl_errno($ch)) {
		//echo 'Error:' . curl_error($ch);
		return false;
	} else {
		// Decode and display the response
		return true;
	}

	// Close cURL connection
    curl_close($ch);
}

function createFile($graphqlUrl, $accessToken, $publicFileUrl) {
	$variables = [
		'files' => [
			'originalSource' => $publicFileUrl
		]
	];
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $graphqlUrl);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['query' => FILE_CREATE_MUTATION, 'variables' => $variables]));
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		'Content-Type: application/json',
		'X-Shopify-Access-Token: ' . $accessToken
	]);

	// Execute the cURL request and get the response
	$response = curl_exec($ch);

	if (curl_errno($ch)) {
		//echo 'Error:' . curl_error($ch);
		return false;
	} else {
		// Decode and display the response
		$responseData = json_decode($response, true);
		return $responseData['data']['fileCreate']['files'][0];
	}

	// Close cURL connection
	curl_close($ch);
}

function getFile($graphqlUrl, $accessToken, $fileCreateResponse) {
	$variables = [
		'id' => $fileCreateResponse['id']
	];

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $graphqlUrl);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['query' => GET_FILE_BY_ID, 'variables' => $variables]));
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		'Content-Type: application/json',
		'X-Shopify-Access-Token: ' . $accessToken
	]);

	// Execute the cURL request and get the response
	$response = curl_exec($ch);

	if (curl_errno($ch)) {
		//echo 'Error:' . curl_error($ch);
		return false;
	} else {
		// Decode and display the response
		$responseData = json_decode($response, true);
		
		if (isset($responseData['data']['node']['image'])) {
			return $responseData['data']['node']['image']['url'];
		}
		return $responseData['data']['node']['url'];
	}

	// Close cURL connection
	curl_close($ch);
}

// Start Uploading File Process
$stagedTarget = createStageUpload($graphqlUrl, $accessToken, $file);
if (!$stagedTarget) {
	$response = array(
		'success' => false,
		'error' => true,
		'message' => 'Stage upload fail.',
		'data' => NULL
	);
	echo json_encode($response);
	exit;
}

$uploadUrl     = $stagedTarget['url'];
$publicFileUrl = $stagedTarget['resourceUrl'];

// Upload file from stageTarget to File Upload in Shopify
$uploadResponse = uploadStageFile($file, $uploadUrl);
if (!$uploadResponse) {
	$response = array(
		'success' => false,
		'error' => true,
		'message' => 'Failed to upload the file to the staged URL.',
		'data' => NULL
	);
	echo json_encode($response);
	exit;
}

// Create file in shopify
$fileCreateResponse = createFile($graphqlUrl, $accessToken, $publicFileUrl);
if (!$fileCreateResponse) {
	$response = array(
		'success' => false,
		'error' => true,
		'message' => 'Failed to register the file in Shopify.',
		'data' => NULL
	);
	echo json_encode($response);
	exit;
}

sleep(5);
// Get Uploaded File Url from Shopify
$fileUrl = getFile($graphqlUrl, $accessToken, $fileCreateResponse);
if (!$fileUrl) {
	$response = array(
		'success' => false,
		'error' => true,
		'message' => 'Failed to upload the file in Shopify.',
		'data' => NULL
	);
	echo json_encode($response);
	exit;
}

$response = array(
	'success' => true,
	'error' => false,
	'message' => 'File uploaded successfully.',
	'data' => $fileUrl
);

echo json_encode($response);
exit;