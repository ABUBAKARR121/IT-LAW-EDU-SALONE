<?php
require_once 'config.php';
if (!isLoggedIn()) {
    redirect('auth.php?action=login');
}

$db = getDB();
$user_id = $_SESSION['user_id'];
$topic = $_GET['topic'] ?? '';
$action = $_GET['action'] ?? 'topics';

$topics = [
    'Nouns' => ['icon' => 'fa-cube', 'desc' => 'A noun names a person, place, thing, or idea. Examples: teacher, Freetown, book, freedom.'],
    'Pronouns' => ['icon' => 'fa-user', 'desc' => 'A pronoun replaces a noun. Examples: he, she, it, they, mine, yours.'],
    'Verbs' => ['icon' => 'fa-running', 'desc' => 'A verb shows action or state of being. Examples: run, eat, is, are, was.'],
    'Adverbs' => ['icon' => 'fa-tachometer-alt', 'desc' => 'An adverb modifies a verb, adjective, or another adverb. Examples: quickly, very, well.'],
    'Adjectives' => ['icon' => 'fa-paint-brush', 'desc' => 'An adjective describes a noun. Examples: red, tall, beautiful, three.'],
    'Prepositions' => ['icon' => 'fa-arrow-right', 'desc' => 'A preposition shows relationship. Examples: on, under, between, through.'],
    'Conjunctions' => ['icon' => 'fa-link', 'desc' => 'A conjunction joins words or sentences. Examples: and, but, or, because.'],
    'Interjections' => ['icon' => 'fa-exclamation', 'desc' => 'An interjection expresses emotion. Examples: Wow!, Ouch!, Hurray!']
];

$examples = [
    'Nouns' => 'teacher, Freetown, book, happiness, team',
    'Pronouns' => 'I, you, he, she, it, we, they, mine, yours',
    'Verbs' => 'run, eat, sleep, think, is, are, was, have',
    'Adverbs' => 'quickly, very, well, yesterday, here, carefully',
    'Adjectives' => 'red, tall, beautiful, three, happy, old',
    'Prepositions' => 'on, in, at, under, between, through, by',
    'Conjunctions' => 'and, but, or, because, although, so, yet',
    'Interjections' => 'Wow!, Ouch!, Hurray!, Oh!, Alas!, Bravo!'
];

$questions = [];
if ($topic && isset($topics[$topic])) {
    $stmt = $db->prepare("SELECT * FROM quiz_questions WHERE topic = ? ORDER BY RAND() LIMIT 10");
    $stmt->execute([$topic]);
    $questions = $stmt->fetchAll();
}

$score = null;
$results = [];
$show_results = false;

if ($action === 'submit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $topic = $_POST['topic'] ?? '';
    $answers = $_POST['answers'] ?? [];
    $question_ids = $_POST['question_ids'] ?? [];

    if ($topic && !empty($answers)) {
        $correct = 0;
        $total = count($question_ids);
        $placeholders = implode(',', array_fill(0, $total, '?'));
        $stmt = $db->prepare("SELECT * FROM quiz_questions WHERE topic = ? AND id IN ($placeholders)");
        $stmt->execute(array_merge([$topic], $question_ids));
        $all_questions = $stmt->fetchAll();

        foreach ($all_questions as $q) {
            $user_answer = $answers[$q['id']] ?? '';
            $is_correct = ($user_answer === $q['correct_answer']);
            if ($is_correct)
                $correct++;
            $results[] = ['question' => $q, 'user_answer' => $user_answer, 'is_correct' => $is_correct];
        }

        $score = $correct;
        $percentage = ($total > 0) ? round(($correct / $total) * 100, 2) : 0;

        $stmt = $db->prepare("INSERT INTO quiz_attempts (user_id, topic, score, total_questions, percentage) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $topic, $correct, $total, $percentage]);

        $show_results = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz -
        <?= SITE_NAME ?>
    </title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a5276, #2980b9);
            min-height: 100vh
        }

        .quiz-header {
            background: rgba(0, 0, 0, 0.3);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
            flex-wrap: wrap;
            gap: 10px
        }

        .quiz-header a {
            color: white;
            text-decoration: none;
            font-weight: 600
        }

        .container {
            max-width: 900px;
            margin: 30px auto;
            padding: 0 20px
        }

        .topics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px
        }

        .topic-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: #333;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1)
        }

        .topic-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.2)
        }

        .topic-card i {
            font-size: 40px;
            color: #2980b9;
            margin-bottom: 15px
        }

        .topic-card h3 {
            font-size: 18px;
            margin-bottom: 8px
        }

        .topic-card p {
            font-size: 13px;
            color: #666
        }

        .guide-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1)
        }

        .guide-section h3 {
            color: #1a5276;
            margin-bottom: 15px
        }

        .guide-section .examples {
            background: #f0f8ff;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px
        }

        .question-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1)
        }

        .question-card h4 {
            color: #1a5276;
            margin-bottom: 15px
        }

        .options label {
            display: block;
            padding: 12px 15px;
            margin: 8px 0;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s
        }

        .options label:hover {
            border-color: #2980b9;
            background: #f0f8ff
        }

        .options input {
            margin-right: 10px
        }

        .btn {
            padding: 14px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            color: white
        }

        .btn-primary {
            background: #2980b9
        }

        .btn-primary:hover {
            background: #1a5276
        }

        .result-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px
        }

        .score-circle {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: bold;
            margin: 20px auto
        }

        .result-item {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08)
        }

        .result-item.correct {
            border-left: 4px solid #27ae60
        }

        .result-item.wrong {
            border-left: 4px solid #e74c3c
        }

        .correct-answer {
            color: #27ae60;
            font-weight: bold
        }

        .wrong-answer {
            color: #e74c3c;
            font-weight: bold
        }

        .explanation {
            background: #f0f8ff;
            padding: 10px 15px;
            border-radius: 8px;
            margin-top: 10px;
            font-size: 14px
        }

        @media(max-width:600px) {
            .topics-grid {
                grid-template-columns: 1fr 1fr
            }
        }
    </style>
</head>

<body>
    <div class="quiz-header">
        <h2><i class="fas fa-brain"></i> Parts of Speech Quiz</h2><a href="system.php"><i class="fas fa-home"></i>
            Dashboard</a>
    </div>
    <div class="container">
        <?php if ($show_results): ?>
            <div class="result-card">
                <h2>Quiz Complete!</h2>
                <p style="color:#666">Topic:
                    <?= h($topic) ?>
                </p>
                <div class="score-circle"
                    style="background:<?= $percentage >= 70 ? '#d4edda' : '#f8d7da' ?>;color:<?= $percentage >= 70 ? '#27ae60' : '#e74c3c' ?>">
                    <?= $percentage ?>%
                </div>
                <p style="font-size:18px">You got <strong>
                        <?= $score ?>
                    </strong> out of <strong>
                        <?= count($results) ?>
                    </strong> correct</p>
                <?php if ($percentage >= 70): ?>
                    <p style="color:#27ae60"><i class="fas fa-check-circle"></i> Great job! You passed!</p>
                <?php else: ?>
                    <p style="color:#e74c3c"><i class="fas fa-redo"></i> Keep practicing!</p>
                <?php endif; ?>
            </div>
            <h3 style="color:white;margin:20px 0">Review Answers</h3>
            <?php foreach ($results as $r): ?>
                <div class="result-item <?= $r['is_correct'] ? 'correct' : 'wrong' ?>">
                    <h4>
                        <?= h($r['question']['question']) ?>
                    </h4>
                    <p>Your answer: <span class="<?= $r['is_correct'] ? 'correct-answer' : 'wrong-answer' ?>">
                            <?= h($r['user_answer'] ?: 'Not answered') ?>
                        </span></p>
                    <?php if (!$r['is_correct']): ?>
                        <p>Correct: <span class="correct-answer">
                                <?= h($r['question']['correct_answer']) ?>)
                                <?= h($r['question']['option_' . strtolower($r['question']['correct_answer'])]) ?>
                            </span></p>
                    <?php endif; ?>
                    <div class="explanation"><strong>Explanation:</strong>
                        <?= h($r['question']['explanation']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <a href="quiz.php?topic=<?= urlencode($topic) ?>" class="btn btn-primary"><i class="fas fa-redo"></i> Try
                Again</a>
            <a href="quiz.php" class="btn btn-primary" style="background:white;color:#2980b9;margin-left:10px">All
                Topics</a>

        <?php elseif ($topic && !empty($questions)): ?>
            <div class="guide-section">
                <h3><i class="fas <?= $topics[$topic]['icon'] ?>"></i>
                    <?= h($topic) ?> - Quick Guide
                </h3>
                <p>
                    <?= $topics[$topic]['desc'] ?>
                </p>
                <div class="examples"><strong>Examples:</strong>
                    <p style="margin-top:8px;font-size:15px">
                        <?= $examples[$topic] ?? '' ?>
                    </p>
                </div>
            </div>
            <form method="POST" action="quiz.php?action=submit">
                <input type="hidden" name="topic" value="<?= h($topic) ?>">
                <?php foreach ($questions as $q): ?><input type="hidden" name="question_ids[]" value="<?= $q['id'] ?>">
                <?php endforeach; ?>
                <?php foreach ($questions as $q): ?>
                    <div class="question-card">
                        <h4>
                            <?= h($q['question']) ?>
                        </h4>
                        <div class="options">
                            <label><input type="radio" name="answers[<?= $q['id'] ?>]" value="A" required> A)
                                <?= h($q['option_a']) ?>
                            </label>
                            <label><input type="radio" name="answers[<?= $q['id'] ?>]" value="B"> B)
                                <?= h($q['option_b']) ?>
                            </label>
                            <label><input type="radio" name="answers[<?= $q['id'] ?>]" value="C"> C)
                                <?= h($q['option_c']) ?>
                            </label>
                            <label><input type="radio" name="answers[<?= $q['id'] ?>]" value="D"> D)
                                <?= h($q['option_d']) ?>
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check-circle"></i> Submit Answers</button>
            </form>

        <?php else: ?>
            <div class="guide-section">
                <h3><i class="fas fa-book-open"></i> Parts of Speech - Complete Guide</h3>
                <p>Words are categorized into eight parts of speech based on their function in a sentence. Understanding
                    these helps you construct proper sentences. Select a topic below to study the guide and take a quiz.</p>
            </div>
            <div class="topics-grid">
                <?php foreach ($topics as $name => $data): ?>
                    <a href="quiz.php?topic=<?= urlencode($name) ?>" class="topic-card"><i class="fas <?= $data['icon'] ?>"></i>
                        <h3>
                            <?= $name ?>
                        </h3>
                        <p>
                            <?= $data['desc'] ?>
                        </p>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>