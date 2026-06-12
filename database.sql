-- ============================================
-- EduSalone Share - Complete Database Schema
-- Digital Public Good for Sierra Leone
-- Drop existing database and recreate
-- ============================================
CREATE DATABASE edusalone_db;
USE edusalone_db;
-- ============================================
-- USERS TABLE
-- ============================================
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
);
-- ============================================
-- EDUCATION LEVELS TABLE
-- ============================================
CREATE TABLE education_levels (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    category ENUM('Primary', 'JSS', 'SSS', 'University') NOT NULL,
    sort_order INT DEFAULT 0
);
-- ============================================
-- SUBJECTS TABLE
-- ============================================
CREATE TABLE subjects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(50),
    description TEXT,
    icon VARCHAR(50) DEFAULT 'fas fa-book',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
-- ============================================
-- CONTENT TABLE
-- ============================================
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
    content_type ENUM(
        'textbook',
        'lesson_note',
        'worksheet',
        'exam_paper',
        'video',
        'audio',
        'research_paper',
        'other'
    ) DEFAULT 'textbook',
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
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE
    SET NULL,
        FOREIGN KEY (education_level_id) REFERENCES education_levels(id) ON DELETE
    SET NULL,
        FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE
    SET NULL
);
-- ============================================
-- COMMENTS TABLE
-- ============================================
CREATE TABLE comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    content_id INT,
    user_id INT,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
-- ============================================
-- COMMENT LIKES TABLE
-- ============================================
CREATE TABLE comment_likes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    comment_id INT,
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_like (comment_id, user_id),
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
-- ============================================
-- COMMENT REPLIES TABLE
-- ============================================
CREATE TABLE comment_replies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    comment_id INT,
    user_id INT,
    reply TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
-- ============================================
-- RATINGS TABLE
-- ============================================
CREATE TABLE ratings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    content_id INT,
    user_id INT,
    rating TINYINT NOT NULL CHECK(
        rating BETWEEN 1 AND 5
    ),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_rating (content_id, user_id),
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
-- ============================================
-- DOWNLOADS LOG TABLE
-- ============================================
CREATE TABLE download_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    content_id INT,
    user_id INT,
    ip_address VARCHAR(45),
    downloaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE
    SET NULL
);
-- ============================================
-- READING LOGS TABLE
-- ============================================
CREATE TABLE reading_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    content_id INT,
    user_id INT,
    page_reached INT DEFAULT 1,
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE
    SET NULL
);
-- ============================================
-- QUIZ QUESTIONS TABLE
-- ============================================
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
);
-- ============================================
-- QUIZ ATTEMPTS TABLE
-- ============================================
CREATE TABLE quiz_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    topic VARCHAR(100),
    score INT,
    total_questions INT,
    percentage DECIMAL(5, 2),
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
-- ============================================
-- INSERT EDUCATION LEVELS
-- ============================================
INSERT INTO education_levels (name, category, sort_order)
VALUES ('Primary 1', 'Primary', 1),
    ('Primary 2', 'Primary', 2),
    ('Primary 3', 'Primary', 3),
    ('Primary 4', 'Primary', 4),
    ('Primary 5', 'Primary', 5),
    ('Primary 6', 'Primary', 6),
    ('JSS 1', 'JSS', 7),
    ('JSS 2', 'JSS', 8),
    ('JSS 3', 'JSS', 9),
    ('SSS 1', 'SSS', 10),
    ('SSS 2', 'SSS', 11),
    ('SSS 3', 'SSS', 12),
    ('University Year 1', 'University', 13),
    ('University Year 2', 'University', 14),
    ('University Year 3', 'University', 15),
    ('University Year 4', 'University', 16),
    ('Masters', 'University', 17),
    ('PhD', 'University', 18);
-- ============================================
-- INSERT SUBJECTS
-- ============================================
INSERT INTO subjects (name, category, description, icon)
VALUES (
        'Mathematics',
        'Science',
        'Pure and Applied Mathematics',
        'fas fa-calculator'
    ),
    (
        'English Language',
        'Arts',
        'Grammar, Comprehension, Literature',
        'fas fa-language'
    ),
    (
        'Physics',
        'Science',
        'Mechanics, Electricity, Waves',
        'fas fa-atom'
    ),
    (
        'Chemistry',
        'Science',
        'Organic, Inorganic, Physical Chemistry',
        'fas fa-flask'
    ),
    (
        'Biology',
        'Science',
        'Botany, Zoology, Human Biology',
        'fas fa-dna'
    ),
    (
        'Agricultural Science',
        'Science',
        'Farming, Agribusiness',
        'fas fa-seedling'
    ),
    (
        'Integrated Science',
        'Science',
        'General Science for Junior Levels',
        'fas fa-microscope'
    ),
    (
        'Computer Science',
        'Science',
        'Programming, Networking, AI',
        'fas fa-laptop-code'
    ),
    (
        'Geography',
        'Social Science',
        'Physical and Human Geography',
        'fas fa-globe-africa'
    ),
    (
        'History',
        'Social Science',
        'Sierra Leone and World History',
        'fas fa-landmark'
    ),
    (
        'Economics',
        'Social Science',
        'Microeconomics, Macroeconomics',
        'fas fa-chart-line'
    ),
    (
        'Business Studies',
        'Commerce',
        'Accounting, Commerce',
        'fas fa-briefcase'
    ),
    (
        'Civic Education',
        'Social Science',
        'Citizenship, Governance',
        'fas fa-balance-scale'
    ),
    (
        'Engineering',
        'Science',
        'Civil, Mechanical, Electrical',
        'fas fa-cogs'
    ),
    (
        'Medicine',
        'Science',
        'Anatomy, Pharmacology, Surgery',
        'fas fa-stethoscope'
    ),
    (
        'Law',
        'Social Science',
        'Constitutional Law, Criminal Law',
        'fas fa-gavel'
    ),
    (
        'Environmental Science',
        'Science',
        'Ecology, Conservation',
        'fas fa-leaf'
    ),
    (
        'Religious Studies',
        'Arts',
        'Christianity, Islam, Traditional',
        'fas fa-hands-praying'
    );
-- ============================================
-- INSERT ADMIN USER
-- Password: EduSalone@2024#SL
-- ============================================
INSERT INTO users (fullname, email, role, password, district)
VALUES (
        'System Administrator',
        'admin@edusalone.sl',
        'admin',
        '$2y$12$KkF6Z3ZxUqHmRq6ZD5F3ZOqF7U5Z3ZxUqHmRq6ZD5F3ZOqF7U5',
        'Western Area Urban'
    );
-- ============================================
-- INSERT DEMO TEACHER
-- Password: Teacher@2024#SL
-- ============================================
INSERT INTO users (fullname, email, role, password, district)
VALUES (
        'Demo Teacher',
        'teacher@edusalone.sl',
        'teacher',
        '$2y$12$KkF6Z3ZxUqHmRq6ZD5F3ZOqF7U5Z3ZxUqHmRq6ZD5F3ZOqF7U5',
        'Western Area Urban'
    );
-- ============================================
-- INSERT QUIZ QUESTIONS - NOUNS
-- ============================================
INSERT INTO quiz_questions (
        topic,
        question,
        option_a,
        option_b,
        option_c,
        option_d,
        correct_answer,
        explanation
    )
VALUES (
        'Nouns',
        'What is a noun?',
        'A word that describes an action',
        'A word that names a person, place, thing, or idea',
        'A word that modifies a verb',
        'A word that connects sentences',
        'B',
        'A noun is a word that names a person like teacher, place like Freetown, thing like book, or idea like freedom.'
    ),
    (
        'Nouns',
        'Which of the following is a proper noun?',
        'city',
        'Sierra Leone',
        'school',
        'river',
        'B',
        'A proper noun names a specific person, place, or thing and always starts with a capital letter. Sierra Leone is a specific country.'
    ),
    (
        'Nouns',
        'Identify the noun in this sentence: The brave soldier defended his country.',
        'brave',
        'defended',
        'soldier',
        'his',
        'C',
        'Soldier is a noun because it names a person. Brave is an adjective describing the soldier.'
    ),
    (
        'Nouns',
        'Which is a collective noun?',
        'table',
        'team',
        'running',
        'happy',
        'B',
        'A collective noun names a group of people or things. Team refers to a group of players working together.'
    ),
    (
        'Nouns',
        'The word "happiness" is what type of noun?',
        'Proper noun',
        'Concrete noun',
        'Abstract noun',
        'Collective noun',
        'C',
        'Abstract nouns name ideas, feelings, or qualities that cannot be seen or touched. Happiness is a feeling.'
    );
-- ============================================
-- INSERT QUIZ QUESTIONS - PRONOUNS
-- ============================================
INSERT INTO quiz_questions (
        topic,
        question,
        option_a,
        option_b,
        option_c,
        option_d,
        correct_answer,
        explanation
    )
VALUES (
        'Pronouns',
        'What is a pronoun?',
        'A word that replaces a noun',
        'A word that describes a noun',
        'A word that shows action',
        'A word that joins clauses',
        'A',
        'A pronoun takes the place of a noun to avoid repetition. Instead of "John went to John house" we say "John went to his house."'
    ),
    (
        'Pronouns',
        'Which word is a pronoun?',
        'quickly',
        'they',
        'beautiful',
        'under',
        'B',
        'They is a personal pronoun that replaces a plural noun. Quickly is an adverb, beautiful is an adjective.'
    ),
    (
        'Pronouns',
        'Choose the correct pronoun: ___ am going to the market.',
        'Me',
        'I',
        'Myself',
        'Mine',
        'B',
        'I is the correct subject pronoun. We use I when the pronoun is doing the action in the sentence.'
    ),
    (
        'Pronouns',
        'Which is a possessive pronoun?',
        'he',
        'she',
        'mine',
        'they',
        'C',
        'Mine is a possessive pronoun showing ownership. He, she, and they are personal pronouns.'
    ),
    (
        'Pronouns',
        'Replace the noun: The teacher gave the books to the students.',
        'He gave them to us',
        'She gave them to them',
        'It gave it to it',
        'They gave him to her',
        'B',
        'Teacher is singular replaced by she. Books and students are plural replaced by them.'
    );
-- ============================================
-- INSERT QUIZ QUESTIONS - VERBS
-- ============================================
INSERT INTO quiz_questions (
        topic,
        question,
        option_a,
        option_b,
        option_c,
        option_d,
        correct_answer,
        explanation
    )
VALUES (
        'Verbs',
        'What is a verb?',
        'A word that names a person',
        'A word that describes a noun',
        'A word that shows action or state of being',
        'A word that connects words',
        'C',
        'A verb expresses action like run or eat, or a state of being like is or are. Every sentence must have a verb.'
    ),
    (
        'Verbs',
        'Identify the verb: The children played happily in the park.',
        'children',
        'played',
        'happily',
        'park',
        'B',
        'Played is the action verb showing what the children did. It is the past tense of play.'
    ),
    (
        'Verbs',
        'Which is a helping verb?',
        'run',
        'table',
        'has',
        'slowly',
        'C',
        'Has is a helping verb that helps the main verb express tense. Example: She has finished her homework.'
    ),
    (
        'Verbs',
        'Choose the correct verb: The dog ___ loudly every night.',
        'bark',
        'barks',
        'barking',
        'barked',
        'B',
        'Barks is correct because dog is singular third person. Singular subjects take verbs ending in s.'
    ),
    (
        'Verbs',
        'What tense is: I will travel to Bo tomorrow.',
        'Past tense',
        'Present tense',
        'Future tense',
        'Perfect tense',
        'C',
        'Will travel indicates future tense. The word will shows the action will happen in the future.'
    );
-- ============================================
-- INSERT QUIZ QUESTIONS - ADVERBS
-- ============================================
INSERT INTO quiz_questions (
        topic,
        question,
        option_a,
        option_b,
        option_c,
        option_d,
        correct_answer,
        explanation
    )
VALUES (
        'Adverbs',
        'What is an adverb?',
        'A word that modifies a verb, adjective, or another adverb',
        'A word that names a person',
        'A word that connects sentences',
        'A word that replaces a noun',
        'A',
        'An adverb modifies a verb like she runs quickly, an adjective like very beautiful, or another adverb like quite slowly.'
    ),
    (
        'Adverbs',
        'Identify the adverb: She sang beautifully at the concert.',
        'she',
        'sang',
        'beautifully',
        'concert',
        'C',
        'Beautifully is an adverb describing how she sang. Many adverbs end in ly.'
    ),
    (
        'Adverbs',
        'Which word is an adverb of time?',
        'here',
        'yesterday',
        'carefully',
        'very',
        'B',
        'Yesterday tells us when something happened. Here is place, carefully is manner, very is degree.'
    ),
    (
        'Adverbs',
        'Choose the correct adverb: The tortoise moved ___ across the road.',
        'quick',
        'slowly',
        'angry',
        'happy',
        'B',
        'Slowly is the adverb form describing how the tortoise moved. Quick, angry, and happy are adjectives.'
    ),
    (
        'Adverbs',
        'In "He almost missed the bus," what type is "almost"?',
        'Adverb of manner',
        'Adverb of time',
        'Adverb of degree',
        'Adverb of place',
        'C',
        'Almost is an adverb of degree telling us to what extent. It modifies the verb missed.'
    );
-- ============================================
-- INSERT QUIZ QUESTIONS - ADJECTIVES
-- ============================================
INSERT INTO quiz_questions (
        topic,
        question,
        option_a,
        option_b,
        option_c,
        option_d,
        correct_answer,
        explanation
    )
VALUES (
        'Adjectives',
        'What is an adjective?',
        'A word that shows action',
        'A word that describes a noun or pronoun',
        'A word that replaces a noun',
        'A word that joins sentences',
        'B',
        'An adjective describes a noun by giving more information. Examples: red car, tall building, delicious food.'
    ),
    (
        'Adjectives',
        'Identify the adjective: The old man walked slowly down the narrow street.',
        'man',
        'walked',
        'old',
        'slowly',
        'C',
        'Old is an adjective describing the noun man. Slowly is an adverb describing how he walked.'
    ),
    (
        'Adjectives',
        'Which uses a comparative adjective?',
        'She is tall',
        'She is taller than her sister',
        'She is the tallest',
        'She has height',
        'B',
        'Taller is comparative comparing two people. Comparative adjectives usually end in er or use more.'
    ),
    (
        'Adjectives',
        'Choose the correct adjective: This is the ___ day of my life.',
        'good',
        'better',
        'best',
        'well',
        'C',
        'Best is the superlative form used when comparing three or more things. It shows the highest degree.'
    ),
    (
        'Adjectives',
        'How many adjectives in: The small brown dog chased three black cats.',
        '2',
        '3',
        '4',
        '5',
        'C',
        'Four adjectives: small, brown, three, and black. Numbers like three function as adjectives when modifying nouns.'
    );
-- ============================================
-- INSERT QUIZ QUESTIONS - PREPOSITIONS
-- ============================================
INSERT INTO quiz_questions (
        topic,
        question,
        option_a,
        option_b,
        option_c,
        option_d,
        correct_answer,
        explanation
    )
VALUES (
        'Prepositions',
        'What is a preposition?',
        'A word showing relationship between noun and another word',
        'A word describing action',
        'A word naming a thing',
        'A word expressing emotion',
        'A',
        'A preposition shows relationship between a noun and another word. It often indicates location, direction, or time.'
    ),
    (
        'Prepositions',
        'Identify the preposition: The book is on the table.',
        'book',
        'is',
        'on',
        'table',
        'C',
        'On is the preposition showing the relationship between book and table. It tells us the location.'
    ),
    (
        'Prepositions',
        'Which is NOT a preposition?',
        'under',
        'between',
        'running',
        'through',
        'C',
        'Running is a verb form, not a preposition. Under, between, and through are all prepositions.'
    ),
    (
        'Prepositions',
        'Choose the correct preposition: She arrived ___ the airport at noon.',
        'in',
        'at',
        'on',
        'by',
        'B',
        'At is used for specific locations like airport, school, or home. In is for larger areas like cities.'
    ),
    (
        'Prepositions',
        'Fill in: He has been waiting ___ three hours.',
        'since',
        'for',
        'during',
        'while',
        'B',
        'For is used with a duration of time like three hours. Since is used with a specific starting point.'
    );
-- ============================================
-- INSERT QUIZ QUESTIONS - CONJUNCTIONS
-- ============================================
INSERT INTO quiz_questions (
        topic,
        question,
        option_a,
        option_b,
        option_c,
        option_d,
        correct_answer,
        explanation
    )
VALUES (
        'Conjunctions',
        'What is a conjunction?',
        'A word that describes a noun',
        'A word that joins words or sentences',
        'A word that shows action',
        'A word that replaces a noun',
        'B',
        'A conjunction connects words, phrases, or clauses. Common conjunctions are and, but, or, because, and although.'
    ),
    (
        'Conjunctions',
        'Identify the conjunction: I wanted to go but I was tired.',
        'wanted',
        'go',
        'but',
        'tired',
        'C',
        'But is the conjunction joining two ideas. It shows contrast between wanting to go and being tired.'
    ),
    (
        'Conjunctions',
        'Which is a coordinating conjunction?',
        'because',
        'although',
        'and',
        'unless',
        'C',
        'And is a coordinating conjunction. Remember FANBOYS: For, And, Nor, But, Or, Yet, So.'
    ),
    (
        'Conjunctions',
        'Choose the correct conjunction: She studied hard ___ she passed the exam.',
        'but',
        'so',
        'or',
        'yet',
        'B',
        'So shows the result. She studied hard, and as a result, she passed. So connects cause and effect.'
    ),
    (
        'Conjunctions',
        'What type is "because" in: I stayed home because it rained.',
        'Coordinating',
        'Subordinating',
        'Correlative',
        'Compound',
        'B',
        'Because is a subordinating conjunction introducing a dependent clause explaining why. It cannot stand alone.'
    );
-- ============================================
-- INSERT QUIZ QUESTIONS - INTERJECTIONS
-- ============================================
INSERT INTO quiz_questions (
        topic,
        question,
        option_a,
        option_b,
        option_c,
        option_d,
        correct_answer,
        explanation
    )
VALUES (
        'Interjections',
        'What is an interjection?',
        'A word expressing strong emotion',
        'A word connecting sentences',
        'A word describing nouns',
        'A word showing location',
        'A',
        'An interjection is a word or phrase expressing sudden feeling or emotion. Examples: Wow, Oh, Ouch, Hurray.'
    ),
    (
        'Interjections',
        'Identify the interjection: Wow! That was amazing!',
        'that',
        'was',
        'amazing',
        'Wow',
        'D',
        'Wow is the interjection expressing surprise and excitement. Interjections are often followed by exclamation marks.'
    ),
    (
        'Interjections',
        'Which is an interjection of pain?',
        'Hurray',
        'Ouch',
        'Alas',
        'Bravo',
        'B',
        'Ouch expresses sudden pain. Hurray expresses joy, Alas expresses sorrow, and Bravo expresses approval.'
    ),
    (
        'Interjections',
        'Choose the correct interjection: ___ We won the match!',
        'Ouch',
        'Hurray',
        'Hush',
        'Oh no',
        'B',
        'Hurray expresses joy and celebration, appropriate for winning. Ouch is for pain, Hush for quiet.'
    ),
    (
        'Interjections',
        'What punctuation often follows an interjection?',
        'Period',
        'Comma',
        'Exclamation mark',
        'Semicolon',
        'C',
        'Interjections are often followed by exclamation marks to show strong emotion. Mild interjections may use commas.'
    );