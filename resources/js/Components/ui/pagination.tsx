import React from 'react';

interface PaginationProps {
    links: Array<{ url: string | null; label: string; active: boolean }>;
    onPageChange: (url: string) => void;
}

export const Pagination: React.FC<PaginationProps> = ({ links, onPageChange }) => {
    if (!links || links.length === 0) return null;

    return (
        <nav className="flex justify-center mt-4">
            <ul className="inline-flex items-center space-x-2">
                {links.map((link, index) => (
                    <li key={index}>
                        <button
                            onClick={() => link.url && onPageChange(link.url)}
                            disabled={!link.url}
                            className={`px-4 py-2 rounded ${
                                link.active
                                    ? 'bg-blue-600 text-white font-semibold'
                                    : 'bg-gray-200 text-gray-600 hover:bg-gray-300'
                            }`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    </li>
                ))}
            </ul>
        </nav>
    );
};
