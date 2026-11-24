// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Load a JSON file into the models setting of the provider instance form.
 *
 * The file is read in the browser and is not stored. The models setting is checked on the server when the form is saved.
 *
 * @module     aiprovider_genericopenai/modelsfile
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {addNotification} from 'core/notification';
import {getString} from 'core/str';

/** @var {Number} The largest file accepted, in bytes. */
const MAX_SIZE = 512 * 1024;

const Selectors = {
    fileInput: '[data-action="aiprovider_genericopenai-loadmodels"]',
};

/**
 * Show an error message.
 *
 * @param {String} identifier The language string identifier.
 * @param {*} param The language string parameter.
 */
const showError = async(identifier, param) => {
    addNotification({
        message: await getString(identifier, 'aiprovider_genericopenai', param),
        type: 'error',
    });
};

/**
 * Load the selected file into the models setting.
 *
 * @param {HTMLInputElement} input The file input.
 */
const loadFile = async(input) => {
    const file = input.files[0];
    const target = document.getElementById(input.dataset.target);
    if (!file || !target) {
        return;
    }
    if (file.size > MAX_SIZE) {
        await showError('modelsfile:toobig', MAX_SIZE / 1024);
        input.value = '';
        return;
    }

    let text = await file.text();
    try {
        // Tidy the layout. The server checks the content when the form is saved.
        text = JSON.stringify(JSON.parse(text), null, 4);
    } catch (error) {
        await showError('modelsfile:invalidjson', error.message);
        input.value = '';
        return;
    }

    target.value = text;
    // Let the form change checker know the setting changed.
    target.dispatchEvent(new Event('change', {bubbles: true}));
    input.value = '';
};

/**
 * Initialise the file input.
 */
export const init = () => {
    document.addEventListener('change', (e) => {
        const input = e.target.closest(Selectors.fileInput);
        if (input) {
            loadFile(input);
        }
    });
};
