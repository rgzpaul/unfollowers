<?php
session_start();

if (!file_exists('followers')) {
    mkdir('followers', 0777, true);
}

$response = ['success' => false, 'message' => ''];
$page = 'login';

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['prefix'])) {
        $_SESSION['user_prefix'] = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['prefix']);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } elseif (isset($_FILES['jsonFile']) && isset($_SESSION['user_prefix'])) {
        $timestamp = date('Y-m-d_H-i-s');
        $prefix = $_SESSION['user_prefix'];
        $fileName = "followers/{$prefix}_followers_$timestamp.json";

        if (move_uploaded_file($_FILES['jsonFile']['tmp_name'], $fileName)) {
            cleanupOldFiles($prefix);
            $response['success'] = true;
            $response['message'] = 'File uploaded successfully';

            $files = glob("followers/{$prefix}_followers_*.json");
            rsort($files);
            $currentData = json_decode(file_get_contents($files[0]), true);
            $response['current_count'] = count($currentData);

            if (count($files) > 1) {
                $previousData = json_decode(file_get_contents($files[1]), true);

                $currentFollowers = array_map(function ($item) {
                    return $item['string_list_data'][0]['value'];
                }, $currentData);

                $previousFollowers = array_map(function ($item) {
                    return $item['string_list_data'][0]['value'];
                }, $previousData);

                $response['comparison'] = [
                    'unfollowed' => array_values(array_diff($previousFollowers, $currentFollowers)),
                    'new_followers' => array_values(array_diff($currentFollowers, $previousFollowers)),
                    'total_current' => count($currentFollowers),
                    'total_previous' => count($previousFollowers)
                ];
            }
        } else {
            $response['message'] = 'Error uploading file';
        }

        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

// Check if user is logged in
if (isset($_SESSION['user_prefix'])) {
    $page = 'main';
}

function cleanupOldFiles($prefix)
{
    $files = glob("followers/{$prefix}_followers_*.json");
    rsort($files);
    foreach (array_slice($files, 2) as $file) {
        unlink($file);
    }
}

function getLastCount($prefix)
{
    $files = glob("followers/{$prefix}_followers_*.json");
    if (empty($files)) return ['count' => 0, 'timestamp' => null];
    rsort($files);
    $data = json_decode(file_get_contents($files[0]), true);

    preg_match('/followers_(\d{4})-(\d{2})-(\d{2})_(\d{2})-(\d{2})-(\d{2})/', basename($files[0]), $matches);
    if ($matches) {
        $timestamp = sprintf(
            "%s/%s/%s %s:%s",
            $matches[3],
            $matches[2],
            $matches[1],
            $matches[4],
            $matches[5]
        );
    } else {
        $timestamp = 'Unknown';
    }

    return ['count' => count($data), 'timestamp' => $timestamp];
}

function getLastComparison($prefix)
{
    $files = glob("followers/{$prefix}_followers_*.json");
    if (count($files) < 2) return null;

    rsort($files);
    $currentData = json_decode(file_get_contents($files[0]), true);
    $previousData = json_decode(file_get_contents($files[1]), true);

    $currentFollowers = array_map(function ($item) {
        return $item['string_list_data'][0]['value'];
    }, $currentData);

    $previousFollowers = array_map(function ($item) {
        return $item['string_list_data'][0]['value'];
    }, $previousData);

    return [
        'unfollowed' => array_values(array_diff($previousFollowers, $currentFollowers)),
        'new_followers' => array_values(array_diff($currentFollowers, $previousFollowers)),
        'total_current' => count($currentFollowers),
        'total_previous' => count($previousFollowers)
    ];
}

$lastCountData = isset($_SESSION['user_prefix']) ? getLastCount($_SESSION['user_prefix']) : ['count' => 0, 'timestamp' => null];
$lastComparison = isset($_SESSION['user_prefix']) ? getLastComparison($_SESSION['user_prefix']) : null;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instagram Followers Comparison</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen">
    <?php if ($page === 'login'): ?>
        <!-- Login Page -->
        <div class="min-h-screen flex items-center justify-center">
            <div class="bg-white p-8 rounded-lg shadow-md w-96">
                <h1 class="text-2xl font-bold mb-6">Login</h1>
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                    <div class="mb-4">
                        <label class="block text-gray-700 mb-2">Enter your identifier</label>
                        <input type="text" name="prefix" required
                            class="w-full p-2 border rounded"
                            pattern="[a-zA-Z0-9_-]+"
                            title="Only letters, numbers, underscores, and hyphens are allowed">
                    </div>
                    <button type="submit" class="w-full bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                        Login
                    </button>
                </form>
            </div>
        </div>

    <?php else: ?>
        <!-- Main Application -->
        <div class="p-8">
            <div class="max-w-2xl mx-auto">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h1 class="text-2xl font-bold">Instagram Followers Comparison</h1>
                        <a href="?logout=1" class="text-sm text-gray-600 hover:text-gray-900">
                            Logout (<?php echo htmlspecialchars($_SESSION['user_prefix']); ?>)
                        </a>
                    </div>

                    <div class="mb-4 p-4 bg-blue-50 rounded">
                        <h3 class="font-medium">
                            Last Followers Count: <span class="text-blue-600" id="lastCount"><?php echo $lastCountData['count']; ?></span>
                            <?php if ($lastCountData['timestamp']): ?>
                                <div class="text-sm text-gray-600">Last checked: <?php echo $lastCountData['timestamp']; ?></div>
                            <?php endif; ?>
                        </h3>
                    </div>

                    <form id="uploadForm" class="mb-6">
                        <div class="mb-4">
                            <label class="block text-gray-700 mb-2">Upload <a style="text-decoration:underline" href="https://privacycenter.instagram.com/dyi/?entry_point=notification">Followers JSON</a></label>
                            <input type="file" name="jsonFile" accept=".json" class="w-full p-2 border rounded">
                        </div>
                        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                            Upload and Compare
                        </button>
                    </form>

                    <div id="results" class="<?php echo $lastComparison ? '' : 'hidden'; ?>">
                        <h2 class="text-xl font-semibold mb-4">Comparison Results</h2>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-4 bg-gray-50 rounded">
                                <h3 class="font-medium mb-2">New Followers</h3>
                                <ul id="newFollowers" class="list-disc pl-4 text-green-600">
                                    <?php
                                    if ($lastComparison) {
                                        foreach ($lastComparison['new_followers'] as $follower) {
                                            echo "<li>" . htmlspecialchars($follower) . "</li>";
                                        }
                                    }
                                    ?>
                                </ul>
                            </div>
                            <div class="p-4 bg-gray-50 rounded">
                                <h3 class="font-medium mb-2">Unfollowed</h3>
                                <ul id="unfollowed" class="list-disc pl-4 text-red-600">
                                    <?php
                                    if ($lastComparison) {
                                        foreach ($lastComparison['unfollowed'] as $follower) {
                                            echo "<li>" . htmlspecialchars($follower) . "</li>";
                                        }
                                    }
                                    ?>
                                </ul>
                            </div>
                        </div>
                        <div class="mt-4 text-sm text-gray-600">
                            <p id="totalCounts">
                                <?php
                                if ($lastComparison) {
                                    echo "Current followers: {$lastComparison['total_current']} | Previous followers: {$lastComparison['total_previous']}";
                                }
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script>
        $(document).ready(function() {
            $('#uploadForm').on('submit', function(e) {
                e.preventDefault();

                var formData = new FormData();
                formData.append('jsonFile', $('input[type=file]')[0].files[0]);

                $.ajax({
                    url: '',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            $('#lastCount').text(response.current_count);

                            if (response.comparison) {
                                $('#results').removeClass('hidden');

                                $('#newFollowers').empty();
                                response.comparison.new_followers.forEach(function(follower) {
                                    $('#newFollowers').append(`<li>${follower}</li>`);
                                });

                                $('#unfollowed').empty();
                                response.comparison.unfollowed.forEach(function(follower) {
                                    $('#unfollowed').append(`<li>${follower}</li>`);
                                });

                                $('#totalCounts').text(
                                    `Current followers: ${response.comparison.total_current} | ` +
                                    `Previous followers: ${response.comparison.total_previous}`
                                );
                            }
                            alert('File uploaded successfully');
                            // Update the page URL to prevent resubmission
                            window.history.replaceState({}, '', window.location.pathname);
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Error uploading file');
                    }
                });
            });
        });
    </script>
</body>

</html>