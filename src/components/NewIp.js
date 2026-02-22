import React, { useState, useEffect } from 'react';
import axios from 'axios';
import toast from 'react-hot-toast';

const NewIp = ({ onIpAdded }) => {
    
    const [ipaddress, setIpaddress] = useState('');
    const [redirect, setRedirect] = useState('');
    const [startdate, setStartdate] = useState('');
    const [enddate, setEnddate] = useState('');
    const [loader, setLoader] = useState('Save Settings');
    const [categories, setCategories] = useState([]);
    const [editBlockType, setBlockType] = useState("");
    const [editBlockCategory, setBlockCategory] = useState("");        
    const [selectedCategory, setSelectedCategory] = useState('');
    const [pageOption, setPageOption] = useState('');

    useEffect(() => {
        axios.get(`${blocipadwoo.apiUrl}/wp/v2/product_cat`)
            .then(res => {
                setCategories(res.data);
            })
            .catch(() => {
                setCategories([]);
            });
    }, []);

    const handleSubmit = (e) => {
        e.preventDefault();
        setLoader('Saving...');
        const ipRegex = /^(?:(?:25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9]?[0-9])\.){3}(?:25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9]?[0-9])$|^([a-fA-F0-9:]+:+)+[a-fA-F0-9]+$/;
        console.log('IP Address:', ipaddress);
        
        if (!ipRegex.test(ipaddress)) {
            toast.error('Invalid IP address! Please enter a valid IP.', {
                position: 'top-center',
                duration: 4000,
                style: {
                    color: '#D32F2F',
                    marginTop: '20px',
                    padding: '10px',
                }
            });
            setLoader('Save Settings');
            return;
        }

        const start = new Date(startdate);
        const end = new Date(enddate);

        if (end <= start) {
            toast.error('End Date must be later than Start Date', {
                position: 'top-center',
                duration: 4000,
                style: {
                    color: '#D32F2F',
                    marginTop: '20px',
                    padding: '10px',
                }
            });
            setLoader('Save Settings');
            return;
        }
        
        console.log('IP Address:');

        // Determine blocktype and blkcategory
        let blocktype = pageOption;
        let blkcategory = pageOption === 'category' ? selectedCategory : '';

        axios.post(`${blocipadwoo.apiUrl}/wooip/v1/new_ip`, {
            ipaddress,
            blocktype,
            blkcategory,
            redirect,
            startdate,
            enddate
        }, {
            headers: {
                'Content-Type': 'application/json',
                'X-WP-NONCE': blocipadwoo.nonce
            }
        })
        .then((res) => {
            const response = res?.data;

            if (response?.status === 'success') {
                toast.success(response.message, {
                    position: 'top-center',
                    duration: 4000,
                    style: {
                        color: '#2e7d32',
                        marginTop: '20px',
                        padding: '10px',
                    }
                });

                setIpaddress('');
                setRedirect('');
                setStartdate('');
                setEnddate('');
                setBlockType('');
                setBlockCategory('');
                onIpAdded?.(); // Optional callback
            } else if (response?.status === 'error') {
                toast.error(response.message, {
                    position: 'top-center',
                    duration: 4000,
                    style: {
                        color: '#D32F2F',
                        marginTop: '20px',
                        padding: '10px',
                    }
                });
            }

            setLoader('Save Settings');
        })
        .catch((error) => {
            toast.error('Something went wrong. Please try again.', {
                position: 'top-center',
                duration: 4000,
                style: {
                    background: '#fff3cd',
                    color: '#856404',
                    padding: '12px',
                },
            });
            setLoader('Save Settings');
        });
    };

    return (
        <form onSubmit={handleSubmit}>
            <div className="form-section">
                {/* Option Buttons */}
                <div className="form-group">
                    <label>Page Option :</label>
                    <div style={{ display: 'flex', gap: '20px', marginBottom: '10px' }}>
                        <label>
                            <input
                                type="radio"
                                name="pageOption"
                                value="home"
                                checked={pageOption === 'home'}
                                onChange={() => setPageOption('home')}
                            />
                            Home Page
                        </label>
                        <label>
                            <input
                                type="radio"
                                name="pageOption"
                                value="shop"
                                checked={pageOption === 'shop'}
                                onChange={() => setPageOption('shop')}
                            />
                            Shop Page
                        </label>
                        <label>
                            <input
                                type="radio"
                                name="pageOption"
                                value="category"
                                checked={pageOption === 'category'}
                                onChange={() => setPageOption('category')}
                            />
                            Category
                        </label>
                    </div>
                </div>
                {/* Category Dropdown - only show if 'category' is selected */}
                {pageOption === 'category' && (
                    <div className="form-group">
                        <label htmlFor="product-category">Product Category :</label>
                        <select
                            id="product-category"
                            value={selectedCategory}
                            onChange={e => setSelectedCategory(e.target.value)}
                        >
                            <option value="">Select a category</option>
                            {categories.map(cat => (
                                <option key={cat.id} value={cat.name}>{cat.name}</option>
                            ))}
                        </select>
                    </div>
                )}
                <div className="form-group">
                    <label htmlFor="ipaddress">Ip Address :</label>
                    <input
                        type="text"
                        name="ipaddress"
                        id="ipaddress"
                        placeholder="Type your block IP address"
                        value={ipaddress}
                        onChange={(e) => setIpaddress(e.target.value)}
                    />
                </div>
                <div className="form-group">
                    <label htmlFor="redirect">Redirect URL :</label>
                    <input
                        type="url"
                        name="redirect"
                        id="redirect"
                        placeholder="Redirect Url"
                        value={redirect}
                        onChange={(e) => setRedirect(e.target.value)}
                    />
                </div>
                <div className="form-group">
                    <label htmlFor="startdate">Start Date :</label>
                    <input
                        type="date"
                        name="startdate"
                        id="startdate"
                        value={startdate}
                        onChange={(e) => setStartdate(e.target.value)}
                    />            
                </div>
                <div className="form-group">
                    <label htmlFor="enddate">End Date :</label>
                    <input
                        type="date"
                        name="enddate"
                        id="enddate"
                        value={enddate}
                        onChange={(e) => setEnddate(e.target.value)}
                    />
                </div>
                <div className="form-group">
                    <div className="col-sm-4">
                        <button type="submit" className="button button-primary">
                            {loader}
                        </button>
                    </div>
                    <div className="col-sm-8"></div>
                </div>
            </div>                     
        </form>
    )
}
export default NewIp;