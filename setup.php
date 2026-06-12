<?php
echo "<h1>EduSalone Share - Setup</h1>";

$host = 'localhost';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("DROP DATABASE IF EXISTS edusalone_db");
    $pdo->exec("CREATE DATABASE edusalone_db");
    $pdo->exec("USE edusalone_db");

    echo "<p style='color:green;'>Database created successfully.</p>";

    $pdo->exec("
        CREATE TABLE users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            fullname VARCHAR(100) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            phone VARCHAR(20),
            role ENUM('student', 'teacher', 'admin') DEFAULT 'student',
            password VARCHAR(255) NOT NULL,
            reset_token VARCHAR(64) DEFAULT NULL,
            reset_token_expiry DATETIME DEFAULT NULL,
            profile_image VARCHAR(255) DEFAULT 'default.png',
            school_name VARCHAR(150),
            district VARCHAR(50),
            bio TEXT,
            status ENUM('active', 'suspended') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE education_levels (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(50) NOT NULL,
            category ENUM('Primary', 'JSS', 'SSS', 'University') NOT NULL,
            sort_order INT DEFAULT 0
        )
    ");

    $pdo->exec("
        CREATE TABLE subjects (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            category VARCHAR(50),
            description TEXT,
            icon VARCHAR(50) DEFAULT 'fas fa-book',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE content (
            id INT PRIMARY KEY AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            subject_id INT,
            education_level_id INT,
            uploaded_by INT,
            file_path VARCHAR(255) NOT NULL,
            file_type VARCHAR(50),
            file_size BIGINT,
            content_type ENUM('textbook', 'lesson_note', 'worksheet', 'exam_paper', 'video', 'audio', 'research_paper', 'other') DEFAULT 'textbook',
            license VARCHAR(100) DEFAULT 'CC BY-SA 4.0',
            downloads INT DEFAULT 0,
            views INT DEFAULT 0,
            rating FLOAT DEFAULT 0,
            approved TINYINT(1) DEFAULT 0,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            rejection_reason TEXT DEFAULT NULL,
            reviewed_by INT DEFAULT NULL,
            reviewed_at DATETIME DEFAULT NULL,
            tags VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
            FOREIGN KEY (education_level_id) REFERENCES education_levels(id) ON DELETE SET NULL,
            FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
        )
    ");

    $pdo->exec("
        CREATE TABLE comments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            content_id INT,
            user_id INT,
            comment TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    $pdo->exec("
        CREATE TABLE comment_likes (
            id INT PRIMARY KEY AUTO_INCREMENT,
            comment_id INT,
            user_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_like (comment_id, user_id),
            FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    $pdo->exec("
        CREATE TABLE comment_replies (
            id INT PRIMARY KEY AUTO_INCREMENT,
            comment_id INT,
            user_id INT,
            reply TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    $pdo->exec("
        CREATE TABLE ratings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            content_id INT,
            user_id INT,
            rating TINYINT NOT NULL CHECK(rating BETWEEN 1 AND 5),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_rating (content_id, user_id),
            FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    $pdo->exec("
        CREATE TABLE download_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            content_id INT,
            user_id INT,
            ip_address VARCHAR(45),
            downloaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )
    ");

    $pdo->exec("
        CREATE TABLE reading_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            content_id INT,
            user_id INT,
            page_reached INT DEFAULT 1,
            read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )
    ");

    $pdo->exec("
        CREATE TABLE quiz_questions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            topic VARCHAR(100) NOT NULL,
            question TEXT NOT NULL,
            option_a VARCHAR(255) NOT NULL,
            option_b VARCHAR(255) NOT NULL,
            option_c VARCHAR(255) NOT NULL,
            option_d VARCHAR(255) NOT NULL,
            correct_answer CHAR(1) NOT NULL,
            explanation TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE quiz_attempts (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT,
            topic VARCHAR(100),
            score INT,
            total_questions INT,
            percentage DECIMAL(5,2),
            attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    echo "<p style='color:green;'>All tables created successfully.</p>";

    $levels = [
        ['Primary 1', 'Primary', 1],
        ['Primary 2', 'Primary', 2],
        ['Primary 3', 'Primary', 3],
        ['Primary 4', 'Primary', 4],
        ['Primary 5', 'Primary', 5],
        ['Primary 6', 'Primary', 6],
        ['JSS 1', 'JSS', 7],
        ['JSS 2', 'JSS', 8],
        ['JSS 3', 'JSS', 9],
        ['SSS 1', 'SSS', 10],
        ['SSS 2', 'SSS', 11],
        ['SSS 3', 'SSS', 12],
        ['University Year 1', 'University', 13],
        ['University Year 2', 'University', 14],
        ['University Year 3', 'University', 15],
        ['University Year 4', 'University', 16],
        ['Masters', 'University', 17],
        ['PhD', 'University', 18]
    ];
    $stmt = $pdo->prepare("INSERT INTO education_levels (name, category, sort_order) VALUES (?, ?, ?)");
    foreach ($levels as $l)
        $stmt->execute($l);

    $subjects = [
        ['Mathematics', 'Science', 'Pure and Applied Mathematics', 'fas fa-calculator'],
        ['English Language', 'Arts', 'Grammar, Comprehension, Literature', 'fas fa-language'],
        ['Physics', 'Science', 'Mechanics, Electricity, Waves', 'fas fa-atom'],
        ['Chemistry', 'Science', 'Organic, Inorganic, Physical Chemistry', 'fas fa-flask'],
        ['Biology', 'Science', 'Botany, Zoology, Human Biology', 'fas fa-dna'],
        ['Agricultural Science', 'Science', 'Farming, Agribusiness', 'fas fa-seedling'],
        ['Integrated Science', 'Science', 'General Science for Junior Levels', 'fas fa-microscope'],
        ['Computer Science', 'Science', 'Programming, Networking', 'fas fa-laptop-code'],
        ['Geography', 'Social Science', 'Physical and Human Geography', 'fas fa-globe-africa'],
        ['History', 'Social Science', 'Sierra Leone and World History', 'fas fa-landmark'],
        ['Economics', 'Social Science', 'Microeconomics, Macroeconomics', 'fas fa-chart-line'],
        ['Business Studies', 'Commerce', 'Accounting, Commerce', 'fas fa-briefcase'],
        ['Civic Education', 'Social Science', 'Citizenship, Governance', 'fas fa-balance-scale'],
        ['Engineering', 'Science', 'Civil, Mechanical, Electrical', 'fas fa-cogs'],
        ['Medicine', 'Science', 'Anatomy, Pharmacology', 'fas fa-stethoscope'],
        ['Law', 'Social Science', 'Constitutional Law, Criminal Law', 'fas fa-gavel'],
        ['Environmental Science', 'Science', 'Ecology, Conservation', 'fas fa-leaf'],
        ['Religious Studies', 'Arts', 'Christianity, Islam, Traditional', 'fas fa-hands-praying']
    ];
    $stmt = $pdo->prepare("INSERT INTO subjects (name, category, description, icon) VALUES (?, ?, ?, ?)");
    foreach ($subjects as $s)
        $stmt->execute($s);

    $admin_pass = password_hash('EduSalone@2024#SL', PASSWORD_BCRYPT);
    $teacher_pass = password_hash('Teacher@2024#SL', PASSWORD_BCRYPT);

    $pdo->exec("INSERT INTO users (fullname, email, role, password, district) VALUES ('System Administrator', 'admin@edusalone.sl', 'admin', '$admin_pass', 'Western Area Urban')");
    $pdo->exec("INSERT INTO users (fullname, email, role, password, district) VALUES ('Demo Teacher', 'teacher@edusalone.sl', 'teacher', '$teacher_pass', 'Western Area Urban')");

    echo "<p style='color:green;'>Admin and teacher accounts created.</p>";

    $quizzes = [
        ['Nouns', 'What is a noun?', 'A word that describes an action', 'A word that names a person, place, thing, or idea', 'A word that modifies a verb', 'A word that connects sentences', 'B', 'A noun names a person (teacher), place (Freetown), thing (book), or idea (freedom).'],
        ['Nouns', 'Which is a proper noun?', 'city', 'Sierra Leone', 'school', 'river', 'B', 'Proper nouns name specific things and start with capital letters. Sierra Leone is a specific country.'],
        ['Nouns', 'Identify the noun: The brave soldier defended his country.', 'brave', 'defended', 'soldier', 'his', 'C', 'Soldier is a noun naming a person. Brave is an adjective describing the soldier.'],
        ['Nouns', 'Which is a collective noun?', 'table', 'team', 'running', 'happy', 'B', 'A collective noun names a group. Team refers to a group of players.'],
        ['Nouns', 'What type of noun is happiness?', 'Proper noun', 'Concrete noun', 'Abstract noun', 'Collective noun', 'C', 'Abstract nouns name ideas or feelings that cannot be touched. Happiness is a feeling.'],
        ['Pronouns', 'What is a pronoun?', 'A word that replaces a noun', 'A word that describes a noun', 'A word that shows action', 'A word that joins clauses', 'A', 'A pronoun takes the place of a noun. Instead of John went to John house we say John went to his house.'],
        ['Pronouns', 'Which word is a pronoun?', 'quickly', 'they', 'beautiful', 'under', 'B', 'They is a personal pronoun replacing a plural noun.'],
        ['Pronouns', 'Choose the correct pronoun: ___ am going to the market.', 'Me', 'I', 'Myself', 'Mine', 'B', 'I is the subject pronoun used when the pronoun does the action.'],
        ['Pronouns', 'Which is a possessive pronoun?', 'he', 'she', 'mine', 'they', 'C', 'Mine shows ownership. He, she, they are personal pronouns.'],
        ['Pronouns', 'Replace the noun: The teacher gave the books to the students.', 'He gave them to us', 'She gave them to them', 'It gave it to it', 'They gave him to her', 'B', 'Teacher is singular replaced by she. Books and students are plural replaced by them.'],
        ['Verbs', 'What is a verb?', 'A word that names a person', 'A word that describes a noun', 'A word that shows action or state of being', 'A word that connects words', 'C', 'A verb expresses action (run, eat) or state of being (is, are). Every sentence needs a verb.'],
        ['Verbs', 'Identify the verb: The children played happily in the park.', 'children', 'played', 'happily', 'park', 'B', 'Played is the action verb showing what the children did.'],
        ['Verbs', 'Which is a helping verb?', 'run', 'table', 'has', 'slowly', 'C', 'Has helps the main verb express tense. Example: She has finished.'],
        ['Verbs', 'Choose correctly: The dog ___ loudly every night.', 'bark', 'barks', 'barking', 'barked', 'B', 'Barks is correct because dog is singular third person.'],
        ['Verbs', 'What tense: I will travel to Bo tomorrow.', 'Past', 'Present', 'Future', 'Perfect', 'C', 'Will travel indicates future tense.'],
        ['Adverbs', 'What is an adverb?', 'A word that modifies a verb, adjective, or another adverb', 'A word that names a person', 'A word that connects sentences', 'A word that replaces a noun', 'A', 'An adverb modifies a verb (runs quickly), adjective (very tall), or another adverb (quite slowly).'],
        ['Adverbs', 'Identify the adverb: She sang beautifully.', 'she', 'sang', 'beautifully', 'concert', 'C', 'Beautifully describes how she sang. Many adverbs end in ly.'],
        ['Adverbs', 'Which is an adverb of time?', 'here', 'yesterday', 'carefully', 'very', 'B', 'Yesterday tells when. Here is place, carefully is manner.'],
        ['Adverbs', 'The tortoise moved ___ across the road.', 'quick', 'slowly', 'angry', 'happy', 'B', 'Slowly is the adverb form. Quick, angry, happy are adjectives needing ly.'],
        ['Adverbs', 'In He almost missed the bus, what type is almost?', 'Manner', 'Time', 'Degree', 'Place', 'C', 'Almost is an adverb of degree telling to what extent.'],
        ['Adjectives', 'What is an adjective?', 'A word that shows action', 'A word that describes a noun or pronoun', 'A word that replaces a noun', 'A word that joins sentences', 'B', 'An adjective describes a noun: red car, tall building, delicious food.'],
        ['Adjectives', 'Identify the adjective: The old man walked slowly.', 'man', 'walked', 'old', 'slowly', 'C', 'Old describes the noun man. Slowly is an adverb.'],
        ['Adjectives', 'Which uses a comparative adjective?', 'She is tall', 'She is taller than her sister', 'She is the tallest', 'She has height', 'B', 'Taller compares two people. Comparatives usually end in er.'],
        ['Adjectives', 'This is the ___ day of my life.', 'good', 'better', 'best', 'well', 'C', 'Best is the superlative showing the highest degree.'],
        ['Adjectives', 'How many adjectives: The small brown dog chased three black cats.', '2', '3', '4', '5', 'C', 'Four: small, brown, three, black. Numbers are adjectives when modifying nouns.'],
        ['Prepositions', 'What is a preposition?', 'A word showing relationship between a noun and another word', 'A word describing action', 'A word naming a thing', 'A word expressing emotion', 'A', 'A preposition shows relationship: on the table, under the bed, between friends.'],
        ['Prepositions', 'Identify the preposition: The book is on the table.', 'book', 'is', 'on', 'table', 'C', 'On shows the relationship between book and table.'],
        ['Prepositions', 'Which is NOT a preposition?', 'under', 'between', 'running', 'through', 'C', 'Running is a verb. Under, between, through are prepositions.'],
        ['Prepositions', 'She arrived ___ the airport at noon.', 'in', 'at', 'on', 'by', 'B', 'At is used for specific locations like airport, school, home.'],
        ['Prepositions', 'He has been waiting ___ three hours.', 'since', 'for', 'during', 'while', 'B', 'For is used with a duration. Since is used with a starting point.'],
        ['Conjunctions', 'What is a conjunction?', 'A word that describes a noun', 'A word that joins words or sentences', 'A word that shows action', 'A word that replaces a noun', 'B', 'A conjunction connects: and, but, or, because, although.'],
        ['Conjunctions', 'Identify the conjunction: I wanted to go but I was tired.', 'wanted', 'go', 'but', 'tired', 'C', 'But joins two ideas showing contrast.'],
        ['Conjunctions', 'Which is a coordinating conjunction?', 'because', 'although', 'and', 'unless', 'C', 'And is coordinating. FANBOYS: For, And, Nor, But, Or, Yet, So.'],
        ['Conjunctions', 'She studied hard ___ she passed the exam.', 'but', 'so', 'or', 'yet', 'B', 'So shows result. She studied, and as a result, she passed.'],
        ['Conjunctions', 'What type is because in: I stayed home because it rained.', 'Coordinating', 'Subordinating', 'Correlative', 'Compound', 'B', 'Because is subordinating, introducing a dependent clause.'],
        ['Interjections', 'What is an interjection?', 'A word expressing strong emotion', 'A word connecting sentences', 'A word describing nouns', 'A word showing location', 'A', 'An interjection expresses sudden feeling: Wow!, Ouch!, Hurray!'],
        ['Interjections', 'Identify the interjection: Wow! That was amazing!', 'that', 'was', 'amazing', 'Wow', 'D', 'Wow expresses surprise and excitement.'],
        ['Interjections', 'Which is an interjection of pain?', 'Hurray', 'Ouch', 'Alas', 'Bravo', 'B', 'Ouch expresses sudden pain. Hurray is joy, Alas is sorrow.'],
        ['Interjections', '___ We won the match!', 'Ouch', 'Hurray', 'Hush', 'Oh no', 'B', 'Hurray expresses joy and celebration for winning.'],
        ['Interjections', 'What punctuation often follows an interjection?', 'Period', 'Comma', 'Exclamation mark', 'Semicolon', 'C', 'Exclamation marks show strong emotion. Mild interjections may use commas.']
    ];

    $stmt = $pdo->prepare("INSERT INTO quiz_questions (topic, question, option_a, option_b, option_c, option_d, correct_answer, explanation) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($quizzes as $q)
        $stmt->execute($q);

    echo "<p style='color:green;'>40 quiz questions inserted across 8 topics.</p>";

    echo "<hr>";
    echo "<h2>Setup Complete!</h2>";
    echo "<p><strong>Admin Login:</strong><br>Email: admin@edusalone.sl<br>Password: EduSalone@2024#SL</p>";
    echo "<p><strong>Teacher Login:</strong><br>Email: teacher@edusalone.sl<br>Password: Teacher@2024#SL</p>";
    echo "<p><a href='index.php' style='padding:15px 30px; background:#2980b9; color:white; text-decoration:none; border-radius:8px; font-weight:bold;'>Go to Login Page</a></p>";
    echo "<p style='color:red;'><strong>IMPORTANT:</strong> Delete this setup.php file now for security!</p>";

} catch (PDOException $e) {
    echo "<p style='color:red;'><strong>Error:</strong> " . $e->getMessage() . "</p>";
}
?>