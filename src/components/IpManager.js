import React, { useState, useEffect } from 'react';
import axios from 'axios';
import toast from 'react-hot-toast';
import NewIp from './NewIp';
import ViewIblocklist from './ViewIblocklist';

const IpManager = () => {
    const [blockedIps, setBlockedIps] = useState([]);
    const [loading, setLoading] = useState(true);

    const fetchBlockedIps = () => {
        axios.get(`${blocipadwoo.apiUrl}/wprk/v1/get_blkip`, {
            headers: {
                'Content-Type': 'application/json',
                'X-WP-NONCE': blocipadwoo.nonce
            }
        })
        .then((res) => {
            setBlockedIps(res.data);
            setLoading(false);
        })
        .catch((err) => {
            console.error('Error fetching blocked IPs:', err);
            setLoading(false);
        });
    };

    useEffect(() => {
        fetchBlockedIps();
    }, []);

    return (
        <div>
            <NewIp onIpAdded={fetchBlockedIps} />
            <ViewIblocklist blockedIps={blockedIps} loading={loading} refresh={fetchBlockedIps} />
        </div>
    );
};

export default IpManager;
