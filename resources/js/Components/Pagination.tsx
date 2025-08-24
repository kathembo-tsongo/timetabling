import React from "react";
import { Link } from "@inertiajs/react";

interface PaginationProps {
  links: {
    url: string | null;
    label: string;
    active: boolean;
  }[] | undefined; // Allow undefined for safety
}

const Pagination: React.FC<PaginationProps> = ({ links }) => {
  if (!links || links.length === 0) {
    return null; // Return nothing if links are undefined or empty
  }

  return (
    <div className="mt-4 flex justify-center">
      <nav className="flex items-center space-x-2">
        {links.map((link, index) => (
          <Link
            key={index}
            href={link.url || ""}
            className={`px-3 py-1 rounded ${
              link.active
                ? "bg-blue-500 text-white"
                : "bg-gray-200 text-gray-700 hover:bg-gray-300"
            }`}
            dangerouslySetInnerHTML={{ __html: link.label }}
          />
        ))}
      </nav>
    </div>
  );
};

export default Pagination;
