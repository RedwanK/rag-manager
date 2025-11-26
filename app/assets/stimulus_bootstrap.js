import { startStimulusApp } from '@symfony/stimulus-bridge';

// Auto-register every controller in ./controllers and the UX bridge controllers.
const app = startStimulusApp(require.context('./controllers', true, /\.js$/));

export default app;
