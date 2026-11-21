/*
 * Page « Supprimer des médias » d'un album (administration, super-administrateur) :
 * case « Tout cocher », nombre de médias cochés sur le bouton, confirmation avant l'envoi.
 * Sans JavaScript, le formulaire fonctionne quand même (sans confirmation).
 */
function setup(form) {
    const boxes = [...form.querySelectorAll('input[name="media[]"]')];
    const selectAll = form.querySelector('[data-select-all]');
    const count = form.querySelector('[data-count]');
    const submit = form.querySelector('[data-submit]');

    const refresh = () => {
        const checked = boxes.filter((box) => box.checked).length;
        count.textContent = String(checked);
        submit.disabled = 0 === checked;
        selectAll.checked = checked === boxes.length;
        selectAll.indeterminate = checked > 0 && checked < boxes.length;
    };

    selectAll.addEventListener('change', () => {
        boxes.forEach((box) => { box.checked = selectAll.checked; });
        refresh();
    });
    boxes.forEach((box) => box.addEventListener('change', refresh));

    form.addEventListener('submit', (event) => {
        const checked = boxes.filter((box) => box.checked).length;
        if (!window.confirm(form.dataset.confirm.replace('%count%', String(checked)))) {
            event.preventDefault();
        }
    });

    refresh();
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[data-album-media-delete]').forEach(setup);
});
