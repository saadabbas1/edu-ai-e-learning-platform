# EDU AI — AI-Powered E-Learning Platform

EDU AI is an AI-powered e-learning web application developed as a Software Engineering project. It combines course and lesson management with OpenAI-powered academic assistance.

## Features

- User registration and authentication
- Student and administrator workflows
- Course and lesson management
- AI-generated quizzes from study material
- 5 MCQs, 3 short-answer questions, and 1 long-answer question per generated quiz
- AI-assisted grading for written answers
- Quiz history and submission tracking
- AI academic chatbot
- Study-material upload and contextual chatbot responses
- Bookmarks and notifications
- User profile and password-management features
- Email-based password-reset workflow

## AI functionality

### AI Quiz Generator

Lesson material is sent to the OpenAI API and converted into structured JSON. The backend validates the response, removes duplicate questions, enforces the expected MCQ structure, and attempts JSON repair when model output is malformed.

### AI Quiz Grading

MCQs are graded deterministically against their correct options. Short and long answers are evaluated by the AI against reference answers, producing scores and feedback.

### AI Academic Chatbot

The chatbot maintains short-term session history and can use uploaded study material as contextual information when responding to the learner.

## Technology Stack

- **Frontend:** HTML, CSS, JavaScript
- **Backend:** PHP
- **Database:** MySQL
- **AI:** OpenAI API
- **Email:** PHPMailer
- **Server:** Apache / PHP

## Repository structure

```text
edu_ai/
├── api/                 # Account-related endpoints
├── controllers/         # Application controllers
├── lib/                 # Shared helpers
├── images/              # Application assets
├── sql/                 # Database-related files
├── *.php                # Pages and backend endpoints
├── *.css                # Styles
├── composer.json        # PHP dependencies
├── config.php           # Environment-based configuration
├── .env.example         # Environment variable template
└── .htaccess            # Apache configuration
```

## Local setup

### Requirements

- PHP 8.x
- MySQL
- Apache with `.htaccess` support
- Composer
- OpenAI API access

### Steps

1. Clone the repository.
2. Create a MySQL database named `edu_ai`.
3. Import the database schema used by the application.
4. Install PHP dependencies:

```bash
composer install
```

5. Configure the variables shown in `.env.example` in your server/local environment.
6. Configure Apache/PHP to serve the project.
7. Open EDU AI in your browser.

> Real API keys, email passwords, and other private credentials are intentionally excluded from this repository.

## Security

Never commit API keys, email passwords, database credentials, or other secrets to GitHub. Credentials should be supplied through environment variables.

If credentials have ever been exposed in a public or shared repository, revoke/rotate them before publishing the project.

## Portfolio value

This project demonstrates practical work in full-stack web development and AI integration, including API integration, structured AI output, automated assessment, authentication, database-backed workflows, and user-facing AI functionality.
