import React, { useState } from 'react';
import axios from 'axios';

const ViewIblocklist = ({ blockedIps = [], refresh }) => {
    const [editingIndex, setEditingIndex] = useState(null);
    const [editIp, setEditIp] = useState("");
    const [editRedirect, setRedirect] = useState("");
    const [editstartDate, setStartdate] = useState("");
    const [editendDate, setEnddate] = useState("");
    const [editBlockType, setBlockType] = useState("");
    const [editBlockCategory, setBlockCategory] = useState("");

    const handleDelete = (id) => {
        if (window.confirm("Are you sure you want to delete this blocked IP?")) {
            axios.post(`${blocipadwoo.apiUrl}/wprk/v1/delete_blkip`, { id }, {
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-NONCE': blocipadwoo.nonce
                }
            })
            .then(() => {
                alert("IP deleted successfully!");
                refresh();
            })
            .catch((err) => console.error("Error deleting IP:", err));
        }
    };

    const handleEdit = (index, ip) => {
        setEditingIndex(index);
        setEditIp(ip.ipaddress);
        setBlockType(ip.blocktype); 
        setBlockCategory(ip.blkcategory);
        setRedirect(ip.redirect);
        setStartdate(ip.startdate);
        setEnddate(ip.enddate);
    };

    const handleUpdate = (ip) => {
        const ipRegex = /^(?:(?:25[0-5]|2[0-4][0-9]|1\d{2}|[1-9]?\d)\.){3}(?:25[0-5]|2[0-4][0-9]|1\d{2}|[1-9]?\d)$|^([a-fA-F0-9:]+:+)+[a-fA-F0-9]+$/;
        if (!ipRegex.test(editIp)) {
            alert("❌ Invalid IP address format.");
            return;
        }

        const urlRegex = /^(https?:\/\/)?([\w\-])+(\.[\w\-]+)+[/#?]?.*$/;
        if (editRedirect && !urlRegex.test(editRedirect)) {
            alert("❌ Invalid Redirect URL format.");
            return;
        }

        const start = new Date(editstartDate);
        const end = new Date(editendDate);
        if (end <= start) {
            alert("❌ End date must be later than start date.");
            return;
        }

        axios.post(`${blocipadwoo.apiUrl}/wprk/v1/update_blkip`, {
            id: ip.id,
            ipaddress: editIp,
            blocktype: editBlockType,
            blkcategory: editBlockCategory,
            redirect: editRedirect,
            startdate: editstartDate,
            enddate: editendDate
        }, {
            headers: {
                'Content-Type': 'application/json',
                'X-WP-NONCE': blocipadwoo.nonce
            }
        })
        .then(() => {
            alert("IP updated successfully!");
            setEditingIndex(null);
            refresh();
        })
        .catch((err) => console.error("Error updating IP:", err));
    };

    return (
        <div className="table-container">
            <div className="section-title">Blocked IP List</div>
            <table>
                <thead>
                    <tr>
                        <th>IP Address</th>
                        <th>Redirect URL</th>
                        <th>Block Type</th>
                        <th>Block Category</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    {blockedIps.length > 0 ? (
                        blockedIps.map((ip, index) => (
                            <tr key={ip.id}>
                                <td>
                                    {editingIndex === index ? (
                                        <input type="text" value={editIp} onChange={(e) => setEditIp(e.target.value)} />
                                    ) : (
                                        ip.ipaddress
                                    )}
                                </td>
                                <td>
                                    {editingIndex === index ? (
                                        <input type="text" value={editRedirect} onChange={(e) => setRedirect(e.target.value)} />
                                    ) : (
                                        ip.redirect
                                    )}
                                </td>
                                    <td>
                                    {editingIndex === index ? (
                                        <input type="text" value={editBlockType} onChange={(e) => setBlockType(e.target.value)} />
                                    ) : (
                                        ip.blocktype
                                    )}
                                </td>
                                <td>
                                    {editingIndex === index ? (
                                        <input type="text" value={editBlockCategory} onChange={(e) => setBlockCategory(e.target.value)} />
                                    ) : (
                                        ip.blkcategory
                                    )}
                                </td>

                                <td>
                                    {editingIndex === index ? (
                                        <input type="date" value={editstartDate} onChange={(e) => setStartdate(e.target.value)} />
                                    ) : (
                                        ip.startdate
                                    )}
                                </td>
                                <td>
                                    {editingIndex === index ? (
                                        <input type="date" value={editendDate} onChange={(e) => setEnddate(e.target.value)} />
                                    ) : (
                                        ip.enddate
                                    )}
                                </td>
                                <td>
                                    {editingIndex === index ? (
                                        <button className="btn btn-edit" onClick={() => handleUpdate(ip)}>Update</button>
                                    ) : (
                                        <button className="btn btn-edit" onClick={() => handleEdit(index, ip)}>Edit</button>
                                    )}
                                    <button className="btn btn-delete" onClick={() => handleDelete(ip.id)} style={{ marginLeft: "10px" }}>
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        ))
                    ) : (
                        <tr>
                            <td colSpan="5">No blocked IPs found.</td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
};

export default ViewIblocklist;