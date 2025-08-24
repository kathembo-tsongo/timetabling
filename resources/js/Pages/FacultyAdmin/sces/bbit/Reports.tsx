"use client"

import { useState } from "react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { Head, router } from "@inertiajs/react"
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card"
import { Button } from "@/Components/ui/button"
import { Badge } from "@/Components/ui/badge"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/Components/ui/select"
import { BarChart3, TrendingUp, Users, BookOpen, GraduationCap, Download, PieChart, FileText } from "lucide-react"
import route from "ziggy-js" // Import route from ziggy-js

interface ReportData {
  enrollmentStats: {
    totalEnrollments: number
    activeStudents: number
    completedUnits: number
    pendingEnrollments: number
  }
  semesterComparison: {
    currentSemester: string
    previousSemester: string
    enrollmentGrowth: number
    studentGrowth: number
  }
  unitPopularity: Array<{
    unit_name: string
    unit_code: string
    enrollment_count: number
  }>
  lecturerWorkload: Array<{
    lecturer_name: string
    lecturer_code: string
    units_assigned: number
    total_students: number
  }>
}

interface Semester {
  id: number
  name: string
  is_active: boolean
}

interface Props {
  reportData: ReportData
  semesters: Semester[]
  schoolCode: string
  schoolName: string
  selectedSemester?: string
}

export default function FacultyReports({ reportData, semesters, schoolCode, schoolName, selectedSemester }: Props) {
  const [currentSemester, setCurrentSemester] = useState(selectedSemester || "")
  const [reportType, setReportType] = useState("overview")

  const handleSemesterChange = (semesterId: string) => {
    setCurrentSemester(semesterId)
    router.get(
      route("faculty.reports." + schoolCode.toLowerCase()),
      {
        semester_id: semesterId,
      },
      {
        preserveState: true,
        replace: true,
      },
    )
  }

  const handleDownloadReport = (format: string) => {
    router.get(route("faculty.reports.download." + schoolCode.toLowerCase()), {
      semester_id: currentSemester,
      type: reportType,
      format: format,
    })
  }

  const renderOverviewCards = () => (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
      <Card>
        <CardContent className="p-6">
          <div className="flex items-center space-x-2">
            <GraduationCap className="w-8 h-8 text-blue-600" />
            <div>
              <p className="text-sm font-medium text-gray-600">Total Enrollments</p>
              <p className="text-2xl font-bold">{reportData.enrollmentStats.totalEnrollments}</p>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardContent className="p-6">
          <div className="flex items-center space-x-2">
            <Users className="w-8 h-8 text-green-600" />
            <div>
              <p className="text-sm font-medium text-gray-600">Active Students</p>
              <p className="text-2xl font-bold">{reportData.enrollmentStats.activeStudents}</p>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardContent className="p-6">
          <div className="flex items-center space-x-2">
            <BookOpen className="w-8 h-8 text-purple-600" />
            <div>
              <p className="text-sm font-medium text-gray-600">Completed Units</p>
              <p className="text-2xl font-bold">{reportData.enrollmentStats.completedUnits}</p>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardContent className="p-6">
          <div className="flex items-center space-x-2">
            <TrendingUp className="w-8 h-8 text-orange-600" />
            <div>
              <p className="text-sm font-medium text-gray-600">Pending Enrollments</p>
              <p className="text-2xl font-bold">{reportData.enrollmentStats.pendingEnrollments}</p>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  )

  const renderSemesterComparison = () => (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center">
          <BarChart3 className="w-5 h-5 mr-2" />
          Semester Comparison
        </CardTitle>
      </CardHeader>
      <CardContent>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div className="space-y-4">
            <div>
              <p className="text-sm text-gray-600">Current Semester</p>
              <p className="text-lg font-semibold">{reportData.semesterComparison.currentSemester}</p>
            </div>
            <div>
              <p className="text-sm text-gray-600">Previous Semester</p>
              <p className="text-lg font-semibold">{reportData.semesterComparison.previousSemester}</p>
            </div>
          </div>
          <div className="space-y-4">
            <div>
              <p className="text-sm text-gray-600">Enrollment Growth</p>
              <div className="flex items-center">
                <p
                  className={`text-lg font-semibold ${reportData.semesterComparison.enrollmentGrowth >= 0 ? "text-green-600" : "text-red-600"}`}
                >
                  {reportData.semesterComparison.enrollmentGrowth >= 0 ? "+" : ""}
                  {reportData.semesterComparison.enrollmentGrowth}%
                </p>
                <TrendingUp
                  className={`w-4 h-4 ml-2 ${reportData.semesterComparison.enrollmentGrowth >= 0 ? "text-green-600" : "text-red-600"}`}
                />
              </div>
            </div>
            <div>
              <p className="text-sm text-gray-600">Student Growth</p>
              <div className="flex items-center">
                <p
                  className={`text-lg font-semibold ${reportData.semesterComparison.studentGrowth >= 0 ? "text-green-600" : "text-red-600"}`}
                >
                  {reportData.semesterComparison.studentGrowth >= 0 ? "+" : ""}
                  {reportData.semesterComparison.studentGrowth}%
                </p>
                <TrendingUp
                  className={`w-4 h-4 ml-2 ${reportData.semesterComparison.studentGrowth >= 0 ? "text-green-600" : "text-red-600"}`}
                />
              </div>
            </div>
          </div>
        </div>
      </CardContent>
    </Card>
  )

  const renderUnitPopularity = () => (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center">
          <PieChart className="w-5 h-5 mr-2" />
          Most Popular Units
        </CardTitle>
      </CardHeader>
      <CardContent>
        <div className="space-y-4">
          {reportData.unitPopularity.map((unit, index) => (
            <div key={unit.unit_code} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
              <div className="flex items-center space-x-3">
                <div
                  className={`w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-bold ${
                    index === 0
                      ? "bg-yellow-500"
                      : index === 1
                        ? "bg-gray-400"
                        : index === 2
                          ? "bg-orange-500"
                          : "bg-blue-500"
                  }`}
                >
                  {index + 1}
                </div>
                <div>
                  <p className="font-medium">{unit.unit_name}</p>
                  <p className="text-sm text-gray-500">{unit.unit_code}</p>
                </div>
              </div>
              <Badge variant="outline">{unit.enrollment_count} students</Badge>
            </div>
          ))}
        </div>
      </CardContent>
    </Card>
  )

  const renderLecturerWorkload = () => (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center">
          <Users className="w-5 h-5 mr-2" />
          Lecturer Workload
        </CardTitle>
      </CardHeader>
      <CardContent>
        <div className="space-y-4">
          {reportData.lecturerWorkload.map((lecturer) => (
            <div key={lecturer.lecturer_code} className="flex items-center justify-between p-3 border rounded-lg">
              <div>
                <p className="font-medium">{lecturer.lecturer_name}</p>
                <p className="text-sm text-gray-500">{lecturer.lecturer_code}</p>
              </div>
              <div className="flex space-x-4 text-sm">
                <div className="text-center">
                  <p className="font-semibold text-blue-600">{lecturer.units_assigned}</p>
                  <p className="text-gray-500">Units</p>
                </div>
                <div className="text-center">
                  <p className="font-semibold text-green-600">{lecturer.total_students}</p>
                  <p className="text-gray-500">Students</p>
                </div>
              </div>
            </div>
          ))}
        </div>
      </CardContent>
    </Card>
  )

  return (
    <AuthenticatedLayout
      header={
        <div className="flex items-center justify-between">
          <h2 className="font-semibold text-xl text-gray-800 leading-tight">{schoolName} - Reports & Analytics</h2>
          <Badge variant="secondary">{schoolCode}</Badge>
        </div>
      }
    >
      <Head title={`${schoolCode} Reports`} />

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
          {/* Controls */}
          <Card>
            <CardContent className="p-6">
              <div className="flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between">
                <div className="flex gap-4">
                  <Select value={currentSemester} onValueChange={handleSemesterChange}>
                    <SelectTrigger className="w-48">
                      <SelectValue placeholder="Select semester" />
                    </SelectTrigger>
                    <SelectContent>
                      {semesters.map((semester) => (
                        <SelectItem key={semester.id} value={semester.id.toString()}>
                          {semester.name} {semester.is_active && <Badge className="ml-2">Active</Badge>}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>

                  <Select value={reportType} onValueChange={setReportType}>
                    <SelectTrigger className="w-48">
                      <SelectValue placeholder="Report type" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="overview">Overview</SelectItem>
                      <SelectItem value="enrollments">Enrollments</SelectItem>
                      <SelectItem value="lecturers">Lecturers</SelectItem>
                      <SelectItem value="units">Units</SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <div className="flex gap-2">
                  <Button variant="outline" onClick={() => handleDownloadReport("pdf")}>
                    <Download className="w-4 h-4 mr-2" />
                    PDF
                  </Button>
                  <Button variant="outline" onClick={() => handleDownloadReport("excel")}>
                    <FileText className="w-4 h-4 mr-2" />
                    Excel
                  </Button>
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Overview Statistics */}
          {renderOverviewCards()}

          {/* Reports Grid */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {renderSemesterComparison()}
            {renderUnitPopularity()}
          </div>

          {/* Lecturer Workload */}
          {renderLecturerWorkload()}
        </div>
      </div>
    </AuthenticatedLayout>
  )
}
