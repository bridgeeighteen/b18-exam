<?php

require_once './includes/db.php';
require_once './includes/version.php';

// Check if it's a POST request
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $userId = $_POST['user_id'];
    $answers = $_POST;

    // Calculate score
    $score = calculateScore($answers);

    // Store results
    if (!is_numeric($score)) {
        error_log("Score is not numeric: " . print_r($score, true));
        // Handle error, e.g., redirect to an error page
        exit;
    }

    // Generate invitation code
    $invitationCode = generateInvitationCode($score);

    if (!isset($invitationCode) || empty($invitationCode)) {
        $invitationCode = '错误：空值'; // Provide a default value
    }

    // Store results
    storeResults($userId, $score, $invitationCode);

    // Record start and end times
    recordTimes($userId);
}

function calculateScore($answers)
{
    $totalQuestions = 0;
    $totalScore = 0; // Initialize total score

    try {
        // Establish database connection
        $conn = connectToDatabase(); // 调用 connectToDatabase 函数

        // Retrieve questions and their answers from the database once
        $stmt = $conn->prepare("SELECT * FROM questions");
        $stmt->execute();
        $result = $stmt->get_result();
        $questions = $result->fetch_all(MYSQLI_ASSOC);

        foreach ($questions as $question) {
            $totalQuestions++;

            // Extract submitted answers for this question
            $submittedAnswers = [];
            foreach ($answers as $key => $value) {
                if (strpos($key, "answer_{$question['id']}") === 0) {
                    if (is_array($value)) { // 处理多选题的数组形式答案
                        $submittedAnswers = array_merge($submittedAnswers, $value);
                    } else {
                        $submittedAnswers[] = $value;
                    }
                }
            }

            // Check if the question was answered
            if (empty($submittedAnswers)) {
                // If no answer was provided, score is 0 for this question
                continue;
            }

            // Normalize the correct answer to match the expected format
            $correctAnswer = str_replace(['(', ')'], '', strtolower($question['answer']));
            
            // Convert all elements to strings before applying strtolower
            $submittedAnswerStr = implode(
                ',', array_map(
                    function ($item) {
                        return is_string($item) ? strtolower($item) : strtolower((string)$item);
                    }, $submittedAnswers
                )
            );

            // Ensure $submittedAnswerStr is not an empty string
            $submittedAnswerStr = $submittedAnswerStr ?: '';

            // Check if the question is a single-choice or multiple-choice
            if (stripos($question['answer'], ',') !== false) {
                // Multiple-choice question
                $scoreForQuestion = 0;
                $correctAnswerParts = explode(',', $correctAnswer);
                $submittedAnswerParts = explode(',', $submittedAnswerStr);

                // Calculate partial score based on the number of correct answers
                $numCorrect = count(array_intersect($correctAnswerParts, $submittedAnswerParts));
                $numIncorrect = count(array_diff($submittedAnswerParts, $correctAnswerParts));

                // Check if the submitted answers include any incorrect choices
                if ($numIncorrect > 0) {
                    $scoreForQuestion = 0; // Score is 0 if any incorrect answer is chosen
                } elseif ($numCorrect > 0) {
                    $scoreForQuestion = SCORE_PARTIAL_MULTIPLE_QUESTION; // Partial score if some correct answers are chosen
                }

                $totalScore += $scoreForQuestion;
            } else {
                // Single-choice question
                if (stripos($correctAnswer, $submittedAnswerStr) !== false) {
                    $totalScore += SCORE_CORRECT_QUESTION; // Full score for single-choice
                }
            }
        }

        // Close the database connection
        closeDatabaseConnection($conn); // 调用 closeDatabaseConnection 函数
    } catch (Exception $e) {
        // Handle exceptions here
        error_log("Error in calculateScore: " . $e->getMessage());
    }

    // Ensure $totalScore is numeric
    $totalScore = (int)$totalScore;

    return $totalScore;
}

function storeResults($userId, $score, $invitationCode)
{
    // Establish database connection
    $conn = connectToDatabase();

    if ($invitationCode === null) {
        $invitationCode = '无（错误）'; // Provide a default value
    }
    // Store the result in the database using prepared statement
    $stmt = $conn->prepare("INSERT INTO results (user_id, score, invitation_code) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $userId, $score, $invitationCode);
    $stmt->execute();

    // Close the database connection
    closeDatabaseConnection($conn);
}

function recordTimes($userId)
{
    // Establish database connection
    $conn = connectToDatabase();

    // Check if the user_id already exists in the results table
    $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM results WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $exists = $row['count'] > 0;

    // Get the start time from the users table
    $stmt = $conn->prepare("SELECT start_time FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    // Get the start time
    $startTime = $user['start_time'];

    // Calculate expected end time
    $expectedEndTime = date('Y-m-d H:i:s', strtotime($startTime . '+' . EXAM_REMAIN_TIME . ' minutes'));

    // Get current server time
    $currentServerTime = date('Y-m-d H:i:s');

    // Check if user violated time limit
    if ($currentServerTime > $expectedEndTime) {
        // Remove result record
        $stmt = $conn->prepare("DELETE FROM results WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();

        // Remove user record
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();

        // User violated time limit
        $timeCheatDetected = true;
    } else {
        if ($exists) {
            // Update the end time for existing user
            $stmt = $conn->prepare("UPDATE results SET end_time = NOW() WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
        } else {
            // Insert a new record for new user
            $stmt = $conn->prepare("INSERT INTO results (user_id, end_time) VALUES (?, NOW())");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
        }
    }

    // Close the database connection
    closeDatabaseConnection($conn);
}

// Extracts the key from the JSON response
function extractKey($data)
{
    try {
        $dataDict = json_decode($data, true);
        $keyValue = $dataDict['data']['attributes']['key'];
        return $keyValue;
    } catch (Exception $e) {
        error_log("Error extracting key: " . $e->getMessage());
        return null;
    }
}

// Generates a random string of specified length containing letters and digits
function generateRandomString($length = 8)
{
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $charsArray = [];
    for ($i = 0; $i < $length; $i++) {
        $charsArray[] = $characters[random_int(0, strlen($characters) - 1)];
    }
    $randomString = implode('', $charsArray);
    $generatedString = CODE_TYPE . "@" . $randomString;
    return $generatedString;
}

// Sends a request to generate a door key
function getDoorKey($key)
{
    $url = 'https://' . API_SITE . '/api/fof/doorkeys';
    $headers = [
        'Content-Type: application/json; charset=UTF-8',
        'Authorization: Token ' . API_X_CSRF_TOKEN,
        'User-Agent: b18-exam/' . VERSION . ' b18-codeget-php/1.0.0',
    ];

    $payload = [
        "data" => [
            "type" => "doorkeys",
            "attributes" => [
                "key" => $key,
                "groupId" => GROUP_ID,
                "maxUses" => MAX_USES,
                "activates" => ACTIVATES
            ]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true); // 使用 true 而不是 1 为了增加可读性
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($error) {
        error_log("cURL 错误：" . $error);
        return null;
    }

    if ($statusCode !== 201) {
        error_log("API 返回异常的 HTTP 状态码：$statusCode");
        return null;
    }

    $responseData = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("收到了未知的 JSON 回应：" . $response);
        return null;
    }

    if (!isset($responseData['data']['attributes']['key'])) {
        error_log("Unexpected response structure: " . print_r($responseData, true));
        return null;
    }

    return $responseData['data']['attributes']['key'];
}
// Main function to generate key
function generateKey()
{
    $key = [];
        $randomNum = generateRandomString();
        $result = getDoorKey($randomNum);
        
    if ($result !== null) {
        $key[] = $result;
    }
    
    return $key;
}
function generateInvitationCode($score)
{
    // Set the score threshold
    $threshold = SCORE_THRESHOLD;

    // Generate an invitation code only if the score is above the threshold
    if ($score >= $threshold) {
        $code = generateKey();
        // Ensure the generated code is a string
        $code = is_array($code) ? implode('', $code) : $code;
    } else {
        $code = '无（未达到条件）';
    }

    return $code;
}

?>

<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>结果 - 十八桥社区论坛入站测试系统</title>
    <link rel="stylesheet" href="./vendor/twbs/bootstrap/dist/css/bootstrap.min.css">
</head>

<?php require './views/nav.php'; ?>
                <div class="card">
                    <div class="card-header">
                        测试结果
                    </div>
                    <div class="card-body">
                        <?php if (isset($timeCheatDetected) && $timeCheatDetected) : ?>
                            <div class="alert alert-danger" role="alert">作弊检测：你违反了测试的时间限制。你的信息已被删除，请重新开始测试。</div>
                            <h5 class="card-title">测试失败。</h5>
                            <p class="card-subtitle">系统检测到你在测试过程中有作弊行为。</p>
                            <p class="card-text">如果对此结果有任何问题，请截屏此页面然后向<a
                            href="mailto:admin@bridge18.rr.nu">管理邮箱</a>发送电子邮件。</p>
                        <?php else: ?>
                            <h5 class="card-title">测试已完成。</h5>
                            <p class="card-subtitle">你的分数是：<strong><?php echo htmlspecialchars($score); ?></strong></p>
                            <p class="card-subtitle">你的邀请码是：<strong><?php echo htmlspecialchars(is_string($invitationCode) ? $invitationCode : '错误：返回内容的类型不是字符串。这有可能是邀请码 API 出现了错误，请截屏并联系管理邮箱获取邀请码。', ENT_QUOTES, 'UTF-8'); ?></strong></p>
                            <p class="card-text">如果对此结果有任何问题，请截屏此页面然后向<a
                                    href="mailto:admin@bridge18.rr.nu">管理邮箱</a>发送电子邮件（被测试者 ID：<strong><?php echo htmlspecialchars(isset($userId) ? $userId : '', ENT_QUOTES, 'UTF-8'); ?></strong>）。</p>
                            <a href="https://www.bridge18.us.kg/" class="btn btn-primary" data-toggle="tooltip"
                                data-placement="top" title="不要忘记写下（或安全地保存）邀请码，它只会出现一次！">去注册</a>
                        <?php endif; ?>
                    </div>
                </div>
<?php require './views/footer.php'; ?>
