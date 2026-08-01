eLearning React LMS (API Driven)
===============================

This folder contains a single-file React + Babel frontend (`index.html`) built on top of:

- `api/student.php` (Student API)
- Browser hash routing (`#/...`) for smooth in-page navigation

Run:

- Make sure XAMPP/Apache is running.
- Open:
  - `http://localhost/edu/react-app/index.html`

Available routes:

- `#/` – Home (for guest) / Dashboard (for logged in user)
- `#/courses` – Course listing
- `#/course/{id}` – Course detail + content/player
- `#/dashboard` – Learner dashboard
- `#/categories` – Category list with sub-categories
- `#/instructors` – Instructor directory (from current users)
- `#/my-courses` – Enrolled courses
- `#/batches` – Live classes + batches
- `#/exams` – Exam list
- `#/exam/{id}` – Exam attempt
- `#/progress` – Progress report
- `#/profile` – Profile + password
- `#/login`, `#/signup`

Login and signup are token-based against `api/student.php`:
- `login`
- `signup`
- `profile`, `courses`, `course`, `purchase`, `update_progress`, etc.
