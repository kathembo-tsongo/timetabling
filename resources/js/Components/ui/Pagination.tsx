import React from 'react';

interface PaginationProps {
    links: {
        url: string | null;
        label: string;
        active: boolean;
    }[];
    onPageChange: (url: string | null) => void;
}

const Pagination: React.FC<PaginationProps> = ({ links, onPageChange }) => {
    return (
        <div className="flex justify-center mt-4 space-x-2">
            {links.map((link, index) => (
                <button
                    key={index}
                    onClick={() => onPageChange(link.url)}
                    className={`px-3 py-1 rounded ${
                        link.active
                            ? 'bg-blue-500 text-white'
                            : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                    }`}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                />
            ))}
        </div>
    );
};

export default Pagination;
