import domready from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';

import App from './App'; 
import NewIp from './components/NewIp';  
import ViewIblocklist from './components/ViewIblocklist';
import IpManager from './components/IpManager';

/* domready( () => {
    const root = createRoot(
        document.getElementById('ip-admin'), 
    );
    root.render(<App />);
});
 */

document.addEventListener("DOMContentLoaded", function() {
    var element = document.getElementById('ip-admin');
        ReactDOM.render(<App />, element);
});

let newip = document.getElementById('new-ipaddress');
if (newip) {
   ReactDOM.render(<IpManager />, newip);
} 

let viewip = document.getElementById('view-iplist');
if (viewip) {
   ReactDOM.render(<ViewIblocklist />, viewip);
} 