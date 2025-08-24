import React from "react";
import { Head, Link } from "@inertiajs/react";

interface Classroom {
  id: number;
  name: string;
  capacity: number;
  location: string;
}

interface Props {
  classroom: Classroom;
}

const Show = ({ classroom }: Props) => {
  return (
    <>
      <Head title={`Classroom: ${classroom.name}`} />
      <div className="p-6 bg-white rounded-lg shadow-md">
        <h1 className="text-2xl font-semibold mb-4">Classroom Details</h1>
        <p>
          <strong>Name:</strong> {classroom.name}
        </p>
        <p>
          <strong>Capacity:</strong> {classroom.capacity}
        </p>
        <p>
          <strong>Location:</strong> {classroom.location}
        </p>
        <div className="mt-4">
          <Link
            href="/classrooms"
            className="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600"
          >
            Back to List
          </Link>
        </div>
      </div>
    </>
  );
};

export default Show;
