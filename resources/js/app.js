import AlpineFlow from '../../vendor/getartisanflow/wireflow/dist/alpineflow.bundle.esm.js';
import AlpineFlowWorkflow from '../../vendor/getartisanflow/wireflow/dist/alpineflow-workflow.esm.js';
//

/**
 * Echo exposes an expressive Api for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';

document.addEventListener('alpine:init', () => {
    window.Alpine.plugin(AlpineFlow);
});
