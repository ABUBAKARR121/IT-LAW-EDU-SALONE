EduSalone Share
Educational Content Sharing Platform for Sierra Leone
A Digital Public Good

WHAT IS THIS PROJECT

EduSalone Share is a web platform where teachers and administrators can upload
and share educational books. Students can read books online or download them
to their phones, tablets, or computers for free. The platform covers all
education levels from Primary 1 to PhD across science, arts, and commerce
subjects.

This project was built for the DLAW207 IT Law and IPR Legal Issues course to
demonstrate legal and ethical software development.

THE PROBLEM IT SOLVES

Students in rural Sierra Leone often lack access to textbooks and quality
learning materials. Teachers in remote schools work alone with no way to
share their best resources. This platform creates a central digital library
where anyone with internet access can find educational content for free.

SDG ALIGNMENT

SDG 4 Quality Education by providing free resources to all students.
SDG 9 Innovation and Infrastructure by building digital education tools.
SDG 10 Reduced Inequalities by bridging the urban rural education gap.

KEY FEATURES

User Registration
Anyone can create an account as a student or teacher with name, email, and
password. Student accounts require no personal information.

Login and Forgot Password
Users log in with email and password. If they forget their password, they
enter their email and receive a reset token that expires in thirty minutes.

Admin Panel
Administrators have a control panel where they review books uploaded by
teachers. They can click Review to preview the book before approving or
rejecting it with a reason. They can also manage users and view statistics.

Book Approval System
When teachers upload books, they go into pending status for admin review.
When administrators upload books, they are published immediately.

Online Book Reader
Students can read books directly on the platform without downloading. The
reader supports PDF files, images, and text files. This saves mobile data.

Download Books
All approved books can be downloaded to any device. Files appear in the
device gallery or file manager and can be opened with any compatible app.

Upload Books
Teachers and admins can upload books with title, description, subject,
education level, content type, tags, and file. Allowed types are PDF, Word,
PowerPoint, Excel, images, videos, audio, and ZIP up to one hundred megabytes.

Star Ratings
Users can rate any book from one to five stars. Each user can rate once but
can change their rating. Average ratings display on book cards.

Comments with Likes and Replies
Users can comment on books. Others can like comments and reply to them,
similar to how Facebook comments work. This creates discussion around
educational materials.

Quiz System
The platform includes a quiz section on the eight parts of speech. Topics
cover Nouns, Pronouns, Verbs, Adverbs, Adjectives, Prepositions, Conjunctions,
and Interjections. Each topic has a study guide with examples before the quiz.
Users get scores and can review their answers with explanations.

Report Generation
Administrators can generate system reports showing total users, books,
downloads, views, comments, ratings, content by subject, content by level,
and top uploaders. Reports can be downloaded as Excel files or printed as PDF.

Profile Management
Users can update their name, phone number, district, school, and biography.

EDUCATION LEVELS

The platform covers Primary 1 through Primary 6, JSS 1 through JSS 3, SSS 1
through SSS 3, University Year 1 through Year 4, Masters, and PhD.

SUBJECTS

Science subjects include Mathematics, Physics, Chemistry, Biology,
Agricultural Science, Integrated Science, Computer Science, Engineering,
Medicine, and Environmental Science.

Social Science includes Geography, History, Economics, Civic Education, and
Law. Commerce includes Business Studies. Arts includes English Language and
Religious Studies.

LICENSE INFORMATION

The software code is released under the MIT License. Anyone can use, modify,
and distribute it freely.

All uploaded educational content is shared under Creative Commons Attribution
ShareAlike 4.0 (CC BY SA 4.0). Anyone can use and adapt the content but must
credit the original creator and share adaptations under the same license.

PRIVACY AND SECURITY

Student accounts collect no personal information beyond a username. Teacher
accounts require only an email for recovery. Passwords are encrypted using
bcrypt. No user data is ever sold or shared. All forms are protected against
CSRF attacks. All database queries use parameterized statements to prevent
SQL injection. Password reset tokens expire after thirty minutes.

TECHNOLOGY USED

Backend is PHP 7.4 or higher. Database is MySQL 5.7 or higher. Frontend uses
HTML5, CSS3, and vanilla JavaScript. Icons are from Font Awesome 6.4.0.

PHP was chosen because it runs on affordable shared hosting available in
Sierra Leone. No expensive cloud services are needed.

HOW TO INSTALL

Step 1
Copy all project files to your web server folder. For XAMPP, place them in
C colon backslash xampp backslash htdocs backslash edusalone

Step 2
Create a folder named uploads inside the edusalone folder and give it write
permissions.

Step 3
Run the setup file by opening your browser and going to
http colon slash slash localhost slash edusalone slash setup.php
This creates the database, all tables, admin account, teacher account, and
quiz questions automatically.

Step 4
Delete setup.php from the server for security.

Step 5
Open http colon slash slash localhost slash edusalone to begin.

ADMIN LOGIN

Email colon admin at edusalone dot sl
Password colon EduSalone at 2024 hash SL

Change this password after your first login through the Profile page.

DEMO TEACHER LOGIN

Email colon teacher at edusalone dot sl
Password colon Teacher at 2024 hash SL

FILE STRUCTURE

index.php is the entry point that redirects based on login status.
config.php holds database settings and helper functions.
auth.php handles login, registration, forgot password, and reset.
system.php is the main platform with dashboard, browse, upload, and profile.
admin.php is the admin panel for approving content and managing users.
review.php lets admins preview teacher uploads before approving or rejecting.
backend.php processes uploads, downloads, ratings, comments, likes, replies.
quiz.php is the parts of speech quiz system.
report.php generates Excel and PDF reports.
reader.php is the online book reader.
logout.php destroys user sessions.
setup.php creates the database and initial data. Delete after running.
uploads folder stores all uploaded educational files.

CONTACT

Ing. Sheku Dinneh Kamara
Email colon sheku dot dkamara at limkokwing dot edu dot sl
