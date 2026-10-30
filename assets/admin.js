/*
 * Point d'entrée de l'administration EasyAdmin (séparé de app.js : pas de styles du site ici).
 */
import './styles/admin.css';
// Jeton CSRF « double-submit » des formulaires Symfony à jeton sans état (ici : l'envoi de médias).
// Sur le site, Stimulus le charge ; l'administration n'a pas Stimulus, on l'importe directement.
import './controllers/csrf_protection_controller.js';
import './admin/media_upload.js';
