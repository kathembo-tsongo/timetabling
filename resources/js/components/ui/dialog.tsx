import React from "react";

export const Dialog = ({ children }: { children: React.ReactNode }) => (
  <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50">
    <div className="bg-white p-6 rounded shadow-md w-96">{children}</div>
  </div>
);

export const DialogTrigger = ({ children, onClick }: { children: React.ReactNode; onClick: () => void }) => (
  <div onClick={onClick} className="inline-block cursor-pointer">
    {children}
  </div>
);

export const DialogContent = ({ children }: { children: React.ReactNode }) => <div>{children}</div>;

export const DialogHeader = ({ children }: { children: React.ReactNode }) => (
  <div className="mb-4 border-b pb-2">
    {typeof children === "string" ? <h2 className="text-lg font-semibold">{children}</h2> : children}
  </div>
);

export const DialogFooter = ({ children }: { children: React.ReactNode }) => (
  <div className="mt-4 flex justify-end space-x-2">{children}</div>
);
