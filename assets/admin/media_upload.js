/*
 * Envoi de médias par lots (page « Ajouter des médias »).
 *
 * PHP n'accepte qu'un nombre limité de fichiers et de mégaoctets par requête (max_file_uploads = 50,
 * post_max_size = 1 Go) : au-delà, il ignore les fichiers en trop sans prévenir. Le navigateur découpe donc la sélection
 * en lots envoyés l'un après l'autre ; chaque lot est traité comme un envoi normal (mêmes album et tags, mêmes
 * contrôles côté serveur), qui répond par un bilan JSON. La page affiche le bilan de tous les lots.
 *
 * Jeton CSRF : csrf_protection_controller.js a déjà, à l'événement « submit » (phase de capture, donc avant ce script),
 * placé un jeton aléatoire dans le champ et le cookie correspondant ; chaque lot les réutilise.
 */
import { generateCsrfHeaders, removeCsrfToken } from '../controllers/csrf_protection_controller.js';

const MAX_FILES_PER_BATCH = 20;
const MAX_BYTES_PER_BATCH = 500 * 1024 * 1024;
const MAX_FILE_BYTES = 256 * 1024 * 1024;
const FILES_FIELD = 'media_upload[files][]';

function formatSize(bytes) {
    return `${(bytes / 1024 / 1024).toLocaleString('fr-BE', { maximumFractionDigits: 0 })} Mo`;
}

function splitIntoBatches(files) {
    const batches = [];
    let current = [];
    let currentBytes = 0;

    for (const file of files) {
        if (current.length > 0 && (current.length >= MAX_FILES_PER_BATCH || currentBytes + file.size > MAX_BYTES_PER_BATCH)) {
            batches.push(current);
            current = [];
            currentBytes = 0;
        }
        current.push(file);
        currentBytes += file.size;
    }
    if (current.length > 0) {
        batches.push(current);
    }

    return batches;
}

/**
 * Zone de message en tête du formulaire. Le texte est toujours inséré avec textContent : les noms de fichiers
 * viennent de l'utilisateur.
 */
function showStatus(form, type, message, details = [], link = null) {
    let status = form.querySelector('[data-upload-status]');
    if (!status) {
        status = document.createElement('div');
        status.dataset.uploadStatus = '';
        status.setAttribute('role', 'status');
        form.prepend(status);
    }
    status.className = `alert alert-${type}`;
    status.replaceChildren(document.createTextNode(message));

    if (details.length > 0) {
        const list = document.createElement('ul');
        list.className = 'mb-0 mt-2';
        for (const detail of details) {
            const item = document.createElement('li');
            item.textContent = detail;
            list.append(item);
        }
        status.append(list);
    }
    if (link) {
        const paragraph = document.createElement('p');
        paragraph.className = 'mb-0 mt-2';
        const anchor = document.createElement('a');
        anchor.href = link.href;
        anchor.textContent = link.label;
        paragraph.append(anchor);
        status.append(paragraph);
    }
}

async function sendBatch(form, input, batch) {
    // Mêmes champs que le formulaire (album, tags, jeton CSRF), seuls les fichiers changent
    input.disabled = false;
    const body = new FormData(form);
    input.disabled = true;
    body.delete(FILES_FIELD);
    batch.forEach((file) => body.append(FILES_FIELD, file, file.name));

    let response;
    try {
        response = await fetch(form.action || window.location.href, {
            method: 'POST',
            body,
            headers: { Accept: 'application/json', ...generateCsrfHeaders(form) },
            credentials: 'same-origin',
        });
    } catch (error) {
        return { failure: 'connexion interrompue' };
    }

    // Session expirée : le pare-feu redirige vers la page de connexion (HTML)
    if (!(response.headers.get('Content-Type') ?? '').includes('application/json')) {
        return { failure: response.redirected ? 'session expirée, reconnectez-vous' : `erreur ${response.status}` };
    }

    const result = await response.json();

    return response.ok ? result : { failure: result.errors.join(' ') || `erreur ${response.status}` };
}

async function uploadInBatches(form, input, files) {
    const button = form.querySelector('button[type="submit"]');
    const listLink = { href: form.dataset.listUrl, label: 'Voir la liste des médias' };

    const tooBig = files.filter((file) => file.size > MAX_FILE_BYTES);
    if (tooBig.length > 0) {
        showStatus(form, 'danger', 'Fichiers trop volumineux (256 Mo maximum chacun), à retirer de la sélection :',
            tooBig.map((file) => `${file.name} (${formatSize(file.size)})`));
        return;
    }

    const batches = splitIntoBatches(files);
    const errors = [];
    let saved = 0;
    let sent = 0;
    button.disabled = true;
    input.disabled = true;

    for (const [index, batch] of batches.entries()) {
        showStatus(form, 'info', `Envoi en cours : lot ${index + 1} sur ${batches.length} (${sent} fichiers envoyés sur ${files.length}). Ne fermez pas la page.`);

        const result = await sendBatch(form, input, batch);
        if (result.failure) {
            showStatus(form, 'danger', `Le lot ${index + 1} sur ${batches.length} n'a pas pu être envoyé (${result.failure}). `
                + `${saved} médias ont été enregistrés avant l'erreur : vérifiez la liste avant de recommencer.`, errors, listLink);
            button.disabled = false;
            input.disabled = false;
            return;
        }

        saved += result.saved;
        errors.push(...result.errors);
        sent += batch.length;
    }

    removeCsrfToken(form);
    input.value = '';
    button.disabled = false;
    input.disabled = false;

    const summary = `${saved} ${saved > 1 ? 'médias ajoutés' : 'média ajouté'}.`;
    if (errors.length > 0) {
        showStatus(form, 'warning', `${summary} ${errors.length} ${errors.length > 1 ? 'fichiers refusés' : 'fichier refusé'} :`, errors, listLink);
    } else {
        showStatus(form, 'success', summary, [], listLink);
    }
}

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.name !== 'media_upload') {
        return;
    }

    const input = form.querySelector(`input[type="file"][name="${FILES_FIELD}"]`);
    const files = input ? Array.from(input.files) : [];

    // Sans fichier, envoi normal : le serveur affiche le message d'erreur du formulaire
    if (files.length === 0) {
        return;
    }

    event.preventDefault();
    uploadInBatches(form, input, files);
});
