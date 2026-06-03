
# Blogravel
## Project Requirements Document (PRD)

## 1. Document Control
### 1.1 Document Metadata
- **Project Name:** Blogravel
- **Author(s):** Alexander Zaborski
- **Status:** 🟠Development 
- **Version:** 0.1
- **Date Created:** 31/05/2026
- **Last Updated:** 31/05/2026
- **Repository Link:** https://github.com/Azab-Inc/blogravel/
- **Webapp Link:** https://app.blogravel.azaber.com/
- **Landing page Link:** https://blogravel.azaber.com/

### 1.2 Version History
| Version | Date | Author | Description of Changes |
| ------- | ---- | ------ | ---------------------- |
| 1.0     |   31/05/2026   | Alexander Zaborski | Initial Draft  |

## 2. Executive Summary
### 2.1 Project Overview
- **Purpose:** A free, self-hosted blogging platform built as a lightweight, privacy-first alternative to WordPress.
- **Summary:** Blogravel is a self-hosted blogging platform that makes it easy to migrate from WordPress and run your own blog without relying on third-party services. It offers one-click WordPress import, bring-your-own-API post generation, and granular mailing list subscriptions.
- **Core Function:** Enabling users to self-host a full-featured blog with seamless WordPress migration, optional AI-assisted content generation via a user-supplied API, and advanced category-level email subscriptions.
- **Target Audience:** Existing WordPress users looking for something less bloated, as well as new bloggers who want a lightweight, self-hosted starting point. Budget: free (self-hosted, infrastructure costs only).
- **Value Proposition:** Free to use, privacy-first by design, easy migration from WordPress via one-click import, and more flexible subscription/mailing list controls than WordPress offers out of the box.

### 2.2 Objectives & Goals
- **Primary Goal:** Solve the friction of leaving WordPress by providing a free, self-hosted alternative with a seamless migration path and modern blogging features.
- **Key Features:**
  - One-click WordPress import (posts, pages, media, categories)
  - Bring-your-own-API integration for AI/automated post generation
  - Granular mailing list subscriptions (readers can subscribe per-category)

## 3. Scope 
### 3.1 Scope for first phase
- [Feature 1: Must-have core feature for the initial release]
- [Feature 2: Must-have core feature for the initial release]
- [Feature 3: Must-have core feature for the initial release]

### 3.2 Scope for Future Phases
- _[Future Feature 1: Nice-to-have feature or enhancement planned for later]_
- _[Future Feature 2: Nice-to-have feature or enhancement planned for later]_

### 3.3 Assumptions & Constraints
- **Assumptions:** [What are we assuming is true about the users?]
- **Constraints:** [What limitations exist? tech stack? ai limits?]

## 4. Functional Requirements
### 4.1 Core Features Breakdown & User Flow
- **Feature Name:** [e.g. Invoice Creation]
  - **Description:** [Brief description of what the feature does]
  - **Dependencies:** [Packages or services required for this to work]
  - **Acceptance Criteria:**
    - [Specific condition that must be met, e.g. User cannot submit form with empty fields]
    - [Specific condition, e.g. Subtotals must recalculate instantly on item edit]

### 4.2 User Flow & Navigation Map
- **Core User Flow:** [Step-by-step path the user takes through the screens, e.g. Dashboard -> Click New Invoice -> Fill Form -> Preview -> Download]
- _**Flowchart / Wireframe Link:**_ [_[Link to diagram or wireframe document]_]

### 4.3 Data Requirements & Schema
- **Key Entities:** [List core tables/data structures]
- **Data Relations:** [e.g x HasMany y]
- **UML:**

## 5. Non-Functional Requirements
### 5.1 Performance & Scalability
- **Performance Targets:** [e.g. Page load under 2 seconds, API response under 200ms]
- _**Scalability:**_ [_[How will the system handle growth? e.g. caching, CDN, DB indexing]_]

### 5.2 Security & Privacy
- **Authentication & Authorization:** [e.g. Cookie-based auth, JWT, or none]
- **Data Protection:** [e.g. HTTPS only, password hashing, encryption of sensitive data]
- _**Compliance Requirements:**_ [_[e.g. GDPR, local privacy laws]_]

### 5.3 Reliability & Availability
- **Uptime:** [e.g. 99% uptime, auto-restart on crash]
- **Backups:** [e.g. Daily automated database backups to external storage]

### 5.4 Compatibility & Accessibility
- **Supported Browsers/OS:** [e.g. Chrome, Firefox, Safari, Edge, Mobile viewports]
- _**Accessibility Standards:**_ [_[e.g. WCAG 2.1 AA compliance, keyboard navigation support]_]

## 6. User Interface & Wireframes
### 6.1 Design tools
- **UI Design Tool:** [e.g Drawio.io, Google Stitch]
- **Styling System:** [e.g. Material Design, Prime UI]

### 6.2 Figma Designs
- _**Figma Link:**_ [_[Link to design file or interactive prototype]_]
- _**Mockups/Wireframes:**_ [Optional: Drag and drop screenshots of the main views below]
  - ![Mockup Placeholder](https://via.placeholder.com/468x300?text=Wireframe+Mockup)

### 6.3 Interactive States & Feedback
- **Hover & Active States:** [e.g. Darken buttons on hover, scale down slightly on click]
- **Transitions & Animations:** [e.g. Smooth 150ms transitions, toast notification slide-ins]
- **Feedback Alerts:** [e.g. Loading spinners on submit, toast notifications for success/error]

## 7. Technical Architecture & Tech Stack
### 7.1 Technical Overview
- **Application Type:** [e.g. REST api, Web app]
- **Hosting solution:** [e.g VPS, Cloud provider (AWS, Azure)]
- **Third-Party Services:** [e.g. SendGrid for emails, Hostinger VPS for server hosting]
- _**API Docs Link:**_ [_[Link to Swagger UI or Postman Collection]_]
### 7.2 Tech Stack
- **Frontend:** [e.g. Blazor, Angular]
  - Libraries: [e.g. Tailwind CSS]
- **Backend:** [e.g. .NET Core, PHP with Symfony]
  - Libraries: [e.g. Entity Framework]
- **Database:** [e.g. SQLite, MySQL, PostgreSQL]
- _**External APIs/SDKs:**_ 
  |API/SDK|Purpose|Relation|
  |-------|-------|--------|
  |e.g Stripe|e.g Payments|e.g CashoutService|

### 7.3 System Architecture Diagram
- _**Diagram Link:**_ [_[Link to architecture flow chart or Miro board]_]
- **Architecture Overview:** [Brief description of how frontend, backend, database, and third-party systems interact]

## 8. Deployment & Infrastructure
### 8.1 Hosting & Environment Strategy
- **Hosting Provider:** [e.g. Hostinger VPS, Cloudflare Pages, AWS]
- **Operating System / Containerization:** [e.g. Debian Linux, Docker container setup]
- **Environments:** [e.g. Development (local), Test/Staging, Production]


### 8.2 CI/CD Pipeline & DevOps
- **CI/CD Platform:** [e.g. GitHub Actions, Jenkins, none (manual deploy)]
- _**Pipelines:**_
  |Pipeline|Platform|Purpose|Order|
  |--------|--------|-------|-----|
  |[e.g Deploy to prod]|[e.g jenkins]|[e.g Upload main to prod]|[e.g Last]|

- **Deployment Process:** [e.g. Automatic on merge to main, or manual run script]

### 8.3 Domain & DNS Configuration
- **Domain Name:** [e.g. azaber.com, profitwithcode.com]
- **Registrar / DNS Manager:** [e.g. Cloudflare, Namecheap]
- **SSL / Security:** [e.g. Let's Encrypt SSL certificates, Cloudflare Proxy]

## 9. Testing & Quality Assurance Plan
### 9.1 Unit Testing Strategy
- **Testing Framework(s):** [e.g. xUnit, Jest, MSTest]
- **Target Areas:** [e.g. Calculation helpers, core utility classes]
- _**Coverage Target:**_ [_[e.g. Minimum 80% coverage on backend logic]_]

### 9.2 Functional Testing
- **Unit Testing:** [e.g. Frameworks used, target areas, mock functions]
- **Integration Testing:** [e.g. Testing database connections, API endpoint integrations]
- **Automated E2E/API Tool:** [e.g. Playwright, Cypress, Postman, none]
- **Critical Paths to Test:** [e.g. Entire checkout flow, invoice generation, user login]
- **Manual Test Cases:** [e.g. Submit form with invalid inputs and verify error validation displays]

### 9.3 Non Functional Testing
- **Performance & Load Testing:** [e.g. Run Lighthouse audit on build, verify page loads in < 1.5s under throttling]
- **Responsiveness Verification:** [e.g. Test interface on mobile, tablet, and desktop viewports]
- _**Accessibility Check:**_ [_[e.g. Validate keyboard navigation and screen reader contrast compliance]_]

## 10. Project Milestones & Timeline
### 10.1 Key Milestones
- **Project Kickoff:** [Date]
- **Core MVP Development:** [Date]
- **Testing & QA:** [Date]
- **Launch Date (v1.0):** [Date]

### 10.2 Release & Rollout Plan
- **Beta Testing:** [e.g. Invite-only beta testing with select users]
- **Production Rollout Strategy:** [e.g. Direct overwrite, zero-downtime blue-green deployment]
- **Post-Launch Monitoring:** [e.g. Verify logs and error reports daily for the first week]

### _10.3 Future Phases_
- _**Phase 2 Features:**_ [_[e.g. Add user accounts, cloud database sync, invoice template customization]_]
- _**Long-term Roadmap:**_ [_[e.g. Native desktop and mobile application versions]_]

## 11. Appendix
### 11.1 Glossary of Terms
- **Term 1:** [Definition of abbreviations or technical terms, e.g. MVP - Minimum Viable Product]
- _**Term 2:**_ [_[Definition of optional/secondary terms]_]

### 11.2 References & External Links
- **Git Standards:** [Git standards guide](file:///home/swagoverlord/repos/wiki/guidelines/git-standards.md)
- _**Third-Party Documentation:**_ [_[Link to external service docs, e.g. Stripe API developer portal]_]
- _**Competitor/Inspiration Links:**_ [_[Link to similar apps or products being used as inspiration]_]