import React from "react";

interface BadgeProps {
  children: React.ReactNode;
  className?: string;
  variant?: "default" | "destructive";
}

export const Badge = ({ children, className = "", variant = "default" }: BadgeProps) => {
  const baseClasses = "inline-flex items-center px-2 py-1 text-xs font-medium rounded";
  const variantClasses =
    variant === "destructive"
      ? "bg-red-100 text-red-800"
      : "bg-green-100 text-green-800";

  return <span className={`${baseClasses} ${variantClasses} ${className}`}>{children}</span>;
};
