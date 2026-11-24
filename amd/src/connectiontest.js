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
 * Test the connection of a saved provider instance by listing its models.
 *
 * @module     aiprovider_genericopenai/connectiontest
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {call as fetchMany} from 'core/ajax';
import {exception as displayException} from 'core/notification';
import Pending from 'core/pending';
import Templates from 'core/templates';
import {addIconToContainer} from 'core/loadingicon';

const Selectors = {
    button: '[data-action="aiprovider_genericopenai-testconnection"]',
    container: '[data-region="aiprovider_genericopenai-connectiontest"]',
    result: '[data-region="aiprovider_genericopenai-connectiontest-result"]',
};

/**
 * Call the test connection web service.
 *
 * @param {Number} providerid The provider instance id.
 * @returns {Promise}
 */
const testConnection = (providerid) => fetchMany([{
    methodname: 'aiprovider_genericopenai_test_connection',
    args: {providerid},
}])[0];

/**
 * Run the test for a button and show the result.
 *
 * @param {HTMLButtonElement} button The test button.
 */
const runTest = async(button) => {
    const result = button.closest(Selectors.container).querySelector(Selectors.result);
    const pending = new Pending('aiprovider_genericopenai/connectiontest');
    button.disabled = true;
    result.innerHTML = '';
    addIconToContainer(result);
    try {
        const response = await testConnection(parseInt(button.dataset.providerid, 10));
        const {html, js} = await Templates.renderForPromise('aiprovider_genericopenai/connection_test_result', {
            ...response,
            hasmodels: response.models.length > 0,
        });
        Templates.replaceNodeContents(result, html, js);
    } catch (error) {
        result.innerHTML = '';
        displayException(error);
    } finally {
        button.disabled = false;
        pending.resolve();
    }
};

/**
 * Initialise the connection test buttons.
 */
export const init = () => {
    document.addEventListener('click', (e) => {
        const button = e.target.closest(Selectors.button);
        if (button) {
            e.preventDefault();
            runTest(button);
        }
    });
};
