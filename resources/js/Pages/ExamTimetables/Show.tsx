import React from "react";
import { Head } from "@inertiajs/react";

const ShowExamTimetable = ({ examTimetable }) => {
    return (
        <div className="container mx-auto p-6">
            <Head title={`Exam Timetable - ${examTimetable.unit_name}`} />
            <h1 className="text-2xl font-bold mb-4">Exam Timetable Details</h1>
            <div className="bg-white shadow-md rounded-lg p-4">
                <p><strong>Unit Code:</strong> {examTimetable.unit.code}</p>
                <p><strong>Unit Name:</strong> {examTimetable.unit.name}</p>
                <p><strong>Semester:</strong> {examTimetable.semester.name}</p>
                <p><strong>Date:</strong> {examTimetable.date}</p>
                <p><strong>Day:</strong> {examTimetable.day}</p>
                <p><strong>Time:</strong> {examTimetable.start_time} - {examTimetable.end_time}</p>
                <p><strong>Venue:</strong> {examTimetable.venue}</p>
                <p><strong>Chief Invigilator:</strong> {examTimetable.chief_invigilator}</p>
            </div>
        </div>
    );
};

export default ShowExamTimetable;
