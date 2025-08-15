<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Load configuration
require_once 'config.php';

// Set timezone
$app_config = Config::getApp();
date_default_timezone_set($app_config['timezone']);

// Create database connection
try {
    $conn = Config::getConnection();
} catch (Exception $e) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Invalid request method']));
}

try {
    // Validate required fields
    $required_fields = ['firstName', 'lastName', 'email', 'artworkTitle', 'medium', 'description'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Required field '$field' is missing");
        }
    }

    // Validate email
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid email address");
    }

    // Validate file uploads
    if (empty($_FILES['artworkImages']['name'][0])) {
        throw new Exception("At least one artwork image is required");
    }

    // Generate unique submission ID
    $submission_id = 'SUB_' . date('Y') . '_' . uniqid();
    
    // Create submission folder
    $submission_folder = 'submissions/' . $submission_id;
    if (!file_exists($submission_folder)) {
        mkdir($submission_folder, 0755, true);
    }

    // Process uploaded files
    $uploaded_files = [];
    $files = $_FILES['artworkImages'];
    $max_file_size = $app_config['max_file_size']; // From .env config
    $max_files = $app_config['max_files_per_submission']; // From .env config
    
    // Check file count limit
    if (count($files['name']) > $max_files) {
        throw new Exception("Maximum $max_files files allowed per submission");
    }
    
    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            // Validate file type
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($files['type'][$i], $allowed_types)) {
                throw new Exception("Invalid file type for " . $files['name'][$i]);
            }

            // Validate file size
            if ($files['size'][$i] > $max_file_size) {
                $max_mb = round($max_file_size / (1024 * 1024), 1);
                throw new Exception("File " . $files['name'][$i] . " is too large. Maximum size is {$max_mb}MB");
            }

            // Generate safe filename
            $file_extension = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
            $safe_filename = 'artwork_' . ($i + 1) . '_' . time() . '.' . $file_extension;
            $file_path = $submission_folder . '/' . $safe_filename;

            // Move uploaded file
            if (move_uploaded_file($files['tmp_name'][$i], $file_path)) {
                $uploaded_files[] = $safe_filename;
            } else {
                throw new Exception("Failed to upload " . $files['name'][$i]);
            }
        } else {
            throw new Exception("Upload error for " . $files['name'][$i]);
        }
    }

    // Prepare data for database
    $submission_data = [
        'submission_id' => $submission_id,
        'first_name' => trim($_POST['firstName']),
        'last_name' => trim($_POST['lastName']),
        'email' => trim($_POST['email']),
        'phone' => trim($_POST['phone'] ?? ''),
        'website' => trim($_POST['website'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'artwork_title' => trim($_POST['artworkTitle']),
        'medium' => trim($_POST['medium']),
        'dimensions' => trim($_POST['dimensions'] ?? ''),
        'year_created' => trim($_POST['yearCreated'] ?? ''),
        'price' => trim($_POST['price'] ?? ''),
        'description' => trim($_POST['description']),
        'artist_statement' => trim($_POST['artistStatement'] ?? ''),
        'image_files' => json_encode($uploaded_files),
        'submission_date' => date('Y-m-d H:i:s'),
        'status' => 'pending'
    ];

    // Insert into database
    $sql = "INSERT INTO submissions (
        submission_id, first_name, last_name, email, phone, website, address,
        artwork_title, medium, dimensions, year_created, price, description,
        artist_statement, image_files, submission_date, status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssssssssssss",
        $submission_data['submission_id'],
        $submission_data['first_name'],
        $submission_data['last_name'],
        $submission_data['email'],
        $submission_data['phone'],
        $submission_data['website'],
        $submission_data['address'],
        $submission_data['artwork_title'],
        $submission_data['medium'],
        $submission_data['dimensions'],
        $submission_data['year_created'],
        $submission_data['price'],
        $submission_data['description'],
        $submission_data['artist_statement'],
        $submission_data['image_files'],
        $submission_data['submission_date'],
        $submission_data['status']
    );

    if (!$stmt->execute()) {
        throw new Exception("Database insertion failed");
    }

    // Create a readable HTML file for the submission
    $html_content = generateSubmissionHTML($submission_data, $uploaded_files, $app_config['site_name']);
    file_put_contents($submission_folder . '/submission_details.html', $html_content);

    // Create a JSON file with submission data
    file_put_contents($submission_folder . '/submission_data.json', json_encode($submission_data, JSON_PRETTY_PRINT));

    // Send email notification if configured
    $email_config = Config::getEmail();
    if ($email_config['notification_email']) {
        sendNotificationEmail($submission_data, $email_config);
    }

    $stmt->close();
    $conn->close();

    echo json_encode([
        'success' => true,
        'message' => 'Submission received successfully',
        'submission_id' => $submission_id
    ]);

} catch (Exception $e) {
    // Clean up uploaded files on error
    if (isset($submission_folder) && file_exists($submission_folder)) {
        $files = glob($submission_folder . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($submission_folder);
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function generateSubmissionHTML($data, $image_files, $site_name = 'Gallery Art Submissions') {
    $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submission Details - ' . htmlspecialchars($data['submission_id']) . '</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            text-align: center;
        }
        .section {
            margin-bottom: 25px;
            padding: 20px;
            border: 1px solid #e1e8ed;
            border-radius: 8px;
        }
        .section h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 5px;
        }
        .field {
            margin-bottom: 10px;
        }
        .field strong {
            color: #2c3e50;
            display: inline-block;
            width: 150px;
        }
        .images {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .images img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
        }
        .status {
            background: #f39c12;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo htmlspecialchars($site_name); ?></h1>
            <p>Submission ID: ' . htmlspecialchars($data['submission_id']) . '</p>
            <p>Submitted: ' . htmlspecialchars($data['submission_date']) . '</p>
            <span class="status">' . ucfirst(htmlspecialchars($data['status'])) . '</span>
        </div>

        <div class="section">
            <h3>Artist Information</h3>
            <div class="field"><strong>Name:</strong> ' . htmlspecialchars($data['first_name'] . ' ' . $data['last_name']) . '</div>
            <div class="field"><strong>Email:</strong> ' . htmlspecialchars($data['email']) . '</div>
            <div class="field"><strong>Phone:</strong> ' . htmlspecialchars($data['phone']) . '</div>
            <div class="field"><strong>Website:</strong> ' . htmlspecialchars($data['website']) . '</div>
            <div class="field"><strong>Address:</strong> ' . nl2br(htmlspecialchars($data['address'])) . '</div>
        </div>

        <div class="section">
            <h3>Artwork Details</h3>
            <div class="field"><strong>Title:</strong> ' . htmlspecialchars($data['artwork_title']) . '</div>
            <div class="field"><strong>Medium:</strong> ' . htmlspecialchars($data['medium']) . '</div>
            <div class="field"><strong>Dimensions:</strong> ' . htmlspecialchars($data['dimensions']) . '</div>
            <div class="field"><strong>Year Created:</strong> ' . htmlspecialchars($data['year_created']) . '</div>
            <div class="field"><strong>Price:</strong> ' . htmlspecialchars($data['price']) . '</div>
            <div class="field"><strong>Description:</strong><br>' . nl2br(htmlspecialchars($data['description'])) . '</div>
            <div class="field"><strong>Artist Statement:</strong><br>' . nl2br(htmlspecialchars($data['artist_statement'])) . '</div>
        </div>

        <div class="section">
            <h3>Artwork Images</h3>
            <div class="images">';
    
    foreach ($image_files as $image) {
        $html .= '<img src="' . htmlspecialchars($image) . '" alt="Artwork Image">';
    }
    
    $html .= '</div>
        </div>
    </div>
</body>
</html>';

    return $html;
}

/**
 * Send email notification for new submission
 */
function sendNotificationEmail($submission_data, $email_config) {
    if (!$email_config['notification_email']) {
        return false;
    }

    $to = $email_config['notification_email'];
    $subject = "New Artist Submission - " . $submission_data['artwork_title'];
    
    $message = "
New artist submission received:

Artist: {$submission_data['first_name']} {$submission_data['last_name']}
Email: {$submission_data['email']}
Artwork: {$submission_data['artwork_title']}
Medium: {$submission_data['medium']}
Submitted: {$submission_data['submission_date']}

Please log in to the admin dashboard to review this submission.
";

    $headers = "From: " . ($email_config['username'] ?: 'noreply@' . $_SERVER['HTTP_HOST']);
    
    try {
        return mail($to, $subject, $message, $headers);
    } catch (Exception $e) {
        // Log error but don't fail the submission
        error_log("Email notification failed: " . $e->getMessage());
        return false;
    }
}
?>