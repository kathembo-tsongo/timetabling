import React from 'react';
import ReactDOM from 'react-dom';
import App from './App';
import Units from './Pages/Units';

if (document.getElementById('root')) {
    ReactDOM.render(<App />, document.getElementById('root'));
}

if (document.getElementById('units')) {
    ReactDOM.render(<Units />, document.getElementById('units'));
}
