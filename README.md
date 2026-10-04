***Note: This single document serves as the definitive Master Blueprint for the entire system, combining architectural design, advanced security protocols, modern UI/UX principles, and deployment instructions.***

# 🚀 Master Development Blueprint: Youth Unity Cup Platform

## Overview & Goals

The goal is to build a highly secure, performant, and user-friendly website using **PHP Vanilla** following a strict Model-View-Controller (MVC) pattern. The site must provide modern UI/UX while supporting complex, dynamic data display (Fixtures, Venue Lists, Registration Status).

---

## I. 💻 Architectural Foundation & Structure

### A. Core Architecture
The application will enforce the MVC separation of concerns to ensure maintainability and testability.

*   **Entry Point:** All requests must be routed through a single file: `public/index.php`.
*   **Routing:** The `Router` component intercepts clean URLs (e.g., `/venue/zone-3`) and maps them directly to the appropriate Controller method, eliminating messy query parameters.

### B. Directory Structure
```markdown
/root
├── public/               <- Entry Point (index.php)
│   └── index.php        <- Router initiation only
|
├── app/                  <- Core Logic & Components
│   ├── Controllers/     <- Handles business logic, calls Models.
│   │   └── VenueController.php 
│   ├── Model/            <- Data interaction (PDO). No display logic here.
│   │   └── VenueModel.php  
│   ├── Services/         <- Centralized utility classes (SecurityService).
│   │   └── SecurityService.php
│   ├── Core/             <- Router, View Engine, Database Connection Singleton.
│   └── Helpers/          <- Utility functions (e.g., date formatters, sanitizers).
|
├── views/                <- All presentation files (Templates)
│   ├── partials/        <- Reusable components (header, footer, card widgets).
│   └── pages/           <- Full page layouts.
|
├── assets/               <- Static resources (CSS, JS, Images)
│   ├── css/
│   ├── js/
│   └── img/
|
└── config/               <- Environment settings and constants.
```

### C. Technical Standards & Best Practices
*   **PHP:** Use modern PHP features (e.g., `declare(strict_types=1)`).
*   **Database Interaction:** Mandatory use of **PDO Prepared Statements** for all queries to prevent SQL Injection.
*   **Autoloading:** Implement `spl_autoload_register` to automatically load class files upon instantiation.

---

## II. ✨ User Interface / Experience (UI/UX)

### A. Design Principles
1.  **Aesthetic:** Modern, clean, and minimalist. Utilize **CSS Grid** and **Flexbox** for robust, responsive layouts.
2.  **Interactivity:** Use subtle hover effects and transitions on all content cards to provide a polished user experience.
3.  **Visual Consistency:** Employ card-based layouts (like the current "icon-box" system) across all sections (Registration status, Venue details).

### B. Frontend Enhancements
*   **Favicon:** The site favicon must be dynamically set using the first letter of the Site Title provided in the Admin settings.
    *(Example: If Site Title is "Youth Unity Cup", use a 'Y' icon).*
*   **Social Integration:** Display prominent, functional social media links (e.g., YouTube) prominently in the header or footer widgets to enhance connectivity.

***Relevant Citation Data Used Here:*** *The presence of `fab fa-youtube` indicates a critical need for integrated social sharing links.*

### C. Content Presentation
*   **Venue List:** Must be structured using clean, semantic HTML tables and dynamically populated via PHP loops in the View layer (e.g., iterating over Zone 1 through Zone 10).
*   **Widgets/Cards:** Use consistent card structures for informational widgets (`icon-box`) to display key selling points (Safety, Registration Status, etc.).

---

## III. ⚙️ Backend Logic & Performance Optimization

### A. Data Flow Cycle
The process is strictly Controller $\rightarrow$ Model $\rightarrow$ View. The Model fetches data; the Controller prepares it; the View displays it.

### B. Performance Optimization (Speed)
1.  **Caching Layer:** Implement **Redis or Memcached** for session data, configuration settings, and frequently requested non-critical query results (e.g., Venue List).
2.  **Asset Bundling:** Use a build process (or manual step) to concatenate and minify all CSS and JavaScript files into minimal bundles.
3.
