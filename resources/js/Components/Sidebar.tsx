import React from 'react';
import { Link } from '@inertiajs/inertia-react';

const Sidebar = () => {
    return (
        <div className="sidebar">
            <Link href="/home">Home</Link>
            <Link href="/about">About</Link>
            <Link href="/contact">Contact</Link>
            <Link href="/faculties">Faculties</Link>
        </div>
    );
};

export default Sidebar;
