"use client"

import type React from "react"
import { useState } from "react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { Head, router } from "@inertiajs/react"
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card"
import { Button } from "@/Components/ui/button"
import { Input } from "@/Components/ui/input"
import { Badge } from "@/Components/ui/badge"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/Components/ui/select"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/ui/table"
import { Calendar, Clock, MapPin, Download, Search, Filter, Eye } from "lucide-react"
import { route } from "vue-router" // Declare the route variable

interface Timetable {
  id: number
  unit_name: string
  unit_code: string
  lecturer_name?: string
  classroom_name?: string
  day_of_week: string
  start_time: string
  end_time: string
  semester_name: string
  group_name?: string
  created_at: string
}

interface Semester {
  id: number
  name: string
  is_active: boolean
}

interface Props {
  timetables: {
    data: Timetable[]
    links: any[]
    total: number
    current_page: number
    per_page: number
  }
  semesters: Semester[]
  schoolCode: string
  schoolName: string
  filters?: {
    search?: string
    semester_id?: string
    day?: string
  }
}

export default function FacultyTimetables({ timetables, semesters, schoolCode, schoolName, filters = {} }: Props) {
  const [searchTerm, setSearchTerm] = useState(filters.search || "")
  const [filterSemester, setFilterSemester] = useState(filters.semester_id || "all") // Update default value to 'all'
  const [filterDay, setFilterDay] = useState(filters.day || "all") // Update default value to 'all'

  const daysOfWeek = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"]

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault()
    router.get(
      route("faculty.timetables." + schoolCode.toLowerCase()),
      {
        search: searchTerm,
        semester_id: filterSemester,
        day: filterDay,
      },
      {
        preserveState: true,
        replace: true,
      },
    )
  }

  const handleDownload = () => {
    router.get(route("faculty.timetable.download." + schoolCode.toLowerCase()), {
      semester_id: filterSemester,
      format: "pdf",
    })
  }

  const formatTime = (time: string) => {
    return new Date(`2000-01-01 ${time}`).toLocaleTimeString("en-US", {
      hour: "numeric",
      minute: "2-digit",
      hour12: true,
    })
  }

  const getDayColor = (day: string) => {
    const colors = {
      Monday: "bg-blue-100 text-blue-800",
      Tuesday: "bg-green-100 text-green-800",
      Wednesday: "bg-yellow-100 text-yellow-800",
      Thursday: "bg-purple-100 text-purple-800",
      Friday: "bg-red-100 text-red-800",
      Saturday: "bg-gray-100 text-gray-800",
      Sunday: "bg-orange-100 text-orange-800",
    }
    return colors[day as keyof typeof colors] || "bg-gray-100 text-gray-800"
  }

  return (
    <AuthenticatedLayout
      header={
        <div className="flex items-center justify-between">
          <h2 className="font-semibold text-xl text-gray-800 leading-tight">{schoolName} - Timetables</h2>
          <Badge variant="secondary">{schoolCode}</Badge>
        </div>
      }
    >
      <Head title={`${schoolCode} Timetables`} />

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
          {/* Statistics Card */}
          <Card>
            <CardContent className="p-6">
              <div className="flex items-center space-x-4">
                <div className="bg-indigo-100 p-3 rounded-full">
                  <Calendar className="w-8 h-8 text-indigo-600" />
                </div>
                <div>
                  <p className="text-sm font-medium text-gray-600">Total Scheduled Classes</p>
                  <p className="text-2xl font-bold">{timetables.total}</p>
                  <p className="text-sm text-gray-500">in {schoolName}</p>
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Filters and Actions */}
          <Card>
            <CardContent className="p-6">
              <div className="flex flex-col lg:flex-row gap-4 items-start lg:items-center justify-between">
                <form onSubmit={handleSearch} className="flex flex-col sm:flex-row gap-2">
                  <div className="relative">
                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                    <Input
                      placeholder="Search timetables..."
                      value={searchTerm}
                      onChange={(e) => setSearchTerm(e.target.value)}
                      className="pl-10 w-64"
                    />
                  </div>

                  <Select value={filterSemester} onValueChange={setFilterSemester}>
                    <SelectTrigger className="w-48">
                      <SelectValue placeholder="Filter by semester" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="all">All Semesters</SelectItem> {/* Update value prop */}
                      {semesters.map((semester) => (
                        <SelectItem key={semester.id} value={semester.id.toString()}>
                          {semester.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>

                  <Select value={filterDay} onValueChange={setFilterDay}>
                    <SelectTrigger className="w-40">
                      <SelectValue placeholder="Filter by day" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="all">All Days</SelectItem> {/* Update value prop */}
                      {daysOfWeek.map((day) => (
                        <SelectItem key={day} value={day}>
                          {day}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>

                  <Button type="submit" variant="outline">
                    <Filter className="w-4 h-4 mr-2" />
                    Filter
                  </Button>
                </form>

                <div className="flex gap-2">
                  <Button onClick={handleDownload} variant="outline">
                    <Download className="w-4 h-4 mr-2" />
                    Download PDF
                  </Button>
                  <Button onClick={() => router.get(route("faculty.timetables.create." + schoolCode.toLowerCase()))}>
                    <Calendar className="w-4 h-4 mr-2" />
                    Create Schedule
                  </Button>
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Timetables Table */}
          <Card>
            <CardHeader>
              <CardTitle>Class Schedules for {schoolName}</CardTitle>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Unit</TableHead>
                    <TableHead>Lecturer</TableHead>
                    <TableHead>Day</TableHead>
                    <TableHead>Time</TableHead>
                    <TableHead>Classroom</TableHead>
                    <TableHead>Group</TableHead>
                    <TableHead>Semester</TableHead>
                    <TableHead>Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {timetables.data.map((timetable) => (
                    <TableRow key={timetable.id}>
                      <TableCell>
                        <div>
                          <p className="font-medium">{timetable.unit_name}</p>
                          <p className="text-sm text-gray-500">{timetable.unit_code}</p>
                        </div>
                      </TableCell>
                      <TableCell>
                        {timetable.lecturer_name ? (
                          <Badge variant="outline">{timetable.lecturer_name}</Badge>
                        ) : (
                          <span className="text-gray-400">Not assigned</span>
                        )}
                      </TableCell>
                      <TableCell>
                        <Badge className={getDayColor(timetable.day_of_week)}>{timetable.day_of_week}</Badge>
                      </TableCell>
                      <TableCell>
                        <div className="flex items-center">
                          <Clock className="w-4 h-4 mr-1 text-blue-600" />
                          <span className="text-sm">
                            {formatTime(timetable.start_time)} - {formatTime(timetable.end_time)}
                          </span>
                        </div>
                      </TableCell>
                      <TableCell>
                        {timetable.classroom_name ? (
                          <div className="flex items-center">
                            <MapPin className="w-4 h-4 mr-1 text-green-600" />
                            {timetable.classroom_name}
                          </div>
                        ) : (
                          <span className="text-gray-400">TBA</span>
                        )}
                      </TableCell>
                      <TableCell>
                        {timetable.group_name ? (
                          <Badge variant="secondary">{timetable.group_name}</Badge>
                        ) : (
                          <span className="text-gray-400">No group</span>
                        )}
                      </TableCell>
                      <TableCell>
                        <Badge variant="outline">{timetable.semester_name}</Badge>
                      </TableCell>
                      <TableCell>
                        <div className="flex space-x-2">
                          <Button
                            size="sm"
                            variant="outline"
                            onClick={() =>
                              router.get(route("faculty.timetables.show." + schoolCode.toLowerCase(), timetable.id))
                            }
                          >
                            <Eye className="w-4 h-4" />
                          </Button>
                        </div>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>

              {timetables.data.length === 0 && (
                <div className="text-center py-8 text-gray-500">
                  <Calendar className="w-12 h-12 mx-auto mb-4 text-gray-300" />
                  <p>No timetables found for {schoolName}.</p>
                  <p className="text-sm">Try adjusting your filters or create a new schedule.</p>
                </div>
              )}

              {/* Pagination would go here if needed */}
            </CardContent>
          </Card>
        </div>
      </div>
    </AuthenticatedLayout>
  )
}
