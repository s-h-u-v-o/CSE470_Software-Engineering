# BioMedOps: Equipment Service-Readiness and Maintenance-Accountability System

**Course:** CSE470 Software Engineering  
**Tech Stack:** Laravel (PHP), MySQL, Blade, Alpine.js

## 📌 Project Overview
BioMedOps is a single-facility diagnostic equipment management system. It tracks equipment from arrival through commissioning, service, repair, and retirement. 

The system is designed to answer three core questions reliably:
1. **Technical Readiness:** Is the machine technically fit for use now, and if not, which record blocks it?
2. **Staffing Readiness:** Is a qualified operator authorized and rostered for it now?
3. **Maintenance Accountability:** Which repairs and preventive tasks are late, who owns them, and how long was the machine down?

*Note: This is an academic prototype. It is not a clinical-safety, regulatory, or accreditation system. All data is synthetic.*

This project prioritizes backend correctness using PHPUnit feature tests.
To run the test suite:
```bash
php artisan test