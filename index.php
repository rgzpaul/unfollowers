<?php
if (!file_exists('followers')) {
    mkdir('followers', 0777, true);
}

$response = ['success' => false, 'message' => ''];

function cleanupOldFiles()
{
    $files = glob('followers/followers_*.json');
    rsort($files);
    foreach (array_slice($files, 2) as $file) {
        unlink($file);
    }
}

function getLastCount()
{
    $files = glob('followers/followers_*.json');
    if (empty($files)) return ['count' => 0, 'timestamp' => null];
    rsort($files);
    $data = json_decode(file_get_contents($files[0]), true);

    // Extract timestamp from filename
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

function getLastComparison()
{
    $files = glob('followers/followers_*.json');
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['jsonFile'])) {
        $timestamp = date('Y-m-d_H-i-s');
        $fileName = "followers/followers_$timestamp.json";

        if (move_uploaded_file($_FILES['jsonFile']['tmp_name'], $fileName)) {
            cleanupOldFiles();
            $response['success'] = true;
            $response['message'] = 'File uploaded successfully';

            $files = glob('followers/followers_*.json');
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
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$lastCountData = getLastCount();
$lastComparison = getLastComparison();
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

<body class="bg-gray-100 min-h-screen p-8">
    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h1 class="text-2xl font-bold mb-6">Instagram Followers Comparison</h1>

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
                                    echo "<li>$follower</li>";
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
                                    echo "<li>$follower</li>";
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
                            location.reload();

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
