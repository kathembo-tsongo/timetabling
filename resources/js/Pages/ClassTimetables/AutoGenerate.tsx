"use client"

import { useState, useEffect } from "react"
import { Head, usePage } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Label } from "@/components/ui/label"
import { Alert, AlertDescription} from "@/components/ui/alert"
import { Badge } from "@/components/ui/badge"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { AlertCircle, Loader2, CheckCircle, Clock, MapPin, Users, GraduationCap, Download } from "lucide-react"
import axios from "axios"
import { toast } from "react-hot-toast"

interface FormData {
  semester_id: string
  program_id: string
  class_id: string
  group_id: string
}

interface TimetableEntry {
  id: number
  day: string
  start_time: string
  end_time: string
  unit_code: string
  unit_name: string
  venue: string
  location: string
  student_count: number
  lecturer: string
  delivery_mode: "Physical" | "Online"
  credit_hours: number
  session_type: string
}

interface ApiData {
  semesters: Array<{ id: number; name: string }>
  programs: Array<{ id: number; code: string; name: string }>
  classes: Array<{ id: number; name: string; program_id: number; semester_id: number }>
  groups: Array<{ id: number; name: string; class_id: number }>
}

export default function AutoGenerateTimetable() {
  const [formData, setFormData] = useState<FormData>({
    semester_id: "",
    program_id: "",
    class_id: "",
    group_id: "",
  })

  const [apiData, setApiData] = useState<ApiData>({
    semesters: [],
    programs: [],
    classes: [],
    groups: [],
  })

  const [isGenerating, setIsGenerating] = useState(false)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const [generatedTimetable, setGeneratedTimetable] = useState<TimetableEntry[]>([])
  const [summary, setSummary] = useState<any>(null)

  const pageProps = usePage().props as any

  // Fetch initial data
  useEffect(() => {
    const fetchInitialData = async () => {
      try {
        setIsLoading(true)
        const [semestersRes] = await Promise.all([fetch("/api/semesters")])

        const semesters = await semestersRes.json()
        setApiData((prev) => ({ ...prev, semesters }))

        console.log("Loaded semesters:", semesters)
      } catch (err) {
        setError("Failed to load initial data")
        console.error("Error fetching initial data:", err)
      } finally {
        setIsLoading(false)
      }
    }

    fetchInitialData()
  }, [])

  // Fetch programs when semester changes
  useEffect(() => {
    if (formData.semester_id) {
      const fetchPrograms = async () => {
        try {
          setIsLoading(true)
          console.log("Fetching programs for semester:", formData.semester_id)

          const response = await axios.get("/api/auto-generate-timetable/programs", {
            params: { semester_id: formData.semester_id },
          })

          console.log("Programs response:", response.data)
          setApiData((prev) => ({ ...prev, programs: response.data }))
        } catch (err) {
          console.error("Error fetching programs:", err)
        } finally {
          setIsLoading(false)
        }
      }

      fetchPrograms()

      // Reset dependent fields
      setFormData((prev) => ({
        ...prev,
        program_id: "",
        class_id: "",
        group_id: "",
      }))
      setApiData((prev) => ({
        ...prev,
        classes: [],
        groups: [],
      }))
    }
  }, [formData.semester_id])

  // Fetch classes when program changes
  useEffect(() => {
    if (formData.program_id && formData.semester_id) {
      const fetchClasses = async () => {
        try {
          setIsLoading(true)
          console.log("Fetching classes for:", {
            semester_id: formData.semester_id,
            program_id: formData.program_id,
          })

          const response = await axios.get("/api/auto-generate-timetable/classes", {
            params: {
              semester_id: formData.semester_id,
              program_id: formData.program_id,
            },
          })

          console.log("Classes response:", response.data)
          setApiData((prev) => ({ ...prev, classes: response.data }))
        } catch (err) {
          console.error("Error fetching classes:", err)
        } finally {
          setIsLoading(false)
        }
      }

      fetchClasses()

      // Reset dependent fields
      setFormData((prev) => ({
        ...prev,
        class_id: "",
        group_id: "",
      }))
      setApiData((prev) => ({
        ...prev,
        groups: [],
      }))
    }
  }, [formData.program_id, formData.semester_id])

  // Fetch groups when class changes
  useEffect(() => {
    if (formData.class_id) {
      const fetchGroups = async () => {
        try {
          setIsLoading(true)
          console.log("Fetching groups for class:", formData.class_id)

          const response = await axios.get("/api/auto-generate-timetable/groups", {
            params: { class_id: formData.class_id },
          })

          console.log("Groups response:", response.data)
          setApiData((prev) => ({ ...prev, groups: response.data }))
        } catch (err) {
          console.error("Error fetching groups:", err)
        } finally {
          setIsLoading(false)
        }
      }

      fetchGroups()

      setFormData((prev) => ({
        ...prev,
        group_id: "",
      }))
    }
  }, [formData.class_id])

  const handleInputChange = (field: keyof FormData, value: string) => {
    setFormData((prev) => {
      const newData = { ...prev, [field]: value }
      return newData
    })

    setError(null)
    setSuccess(null)
  }

  const handleGenerate = async () => {
    if (!formData.semester_id || !formData.program_id || !formData.class_id) {
      setError("Please select semester, program, and class")
      return
    }

    setIsGenerating(true)
    setError(null)
    setSuccess(null)

    try {
      const requestBody = {
        semester_id: Number.parseInt(formData.semester_id),
        program_id: Number.parseInt(formData.program_id),
        class_id: Number.parseInt(formData.class_id),
        ...(formData.group_id && { group_id: Number.parseInt(formData.group_id) }),
      }

      console.log("Sending request:", requestBody)

      const response = await axios.post("/auto-generate-timetable", requestBody)
      console.log("Generation response:", response.data)

      if (response.data.success) {
        setGeneratedTimetable(response.data.data || [])
        setSummary(response.data.summary)
        setSuccess(response.data.message)
        toast.success(response.data.message)
      } else {
        throw new Error(response.data.message || "Generation failed")
      }
    } catch (err: any) {
      const errorMessage = err.response?.data?.message || err.message || "An unexpected error occurred"
      setError(errorMessage)
      toast.error(errorMessage)
      console.error("Generation error:", err)
    } finally {
      setIsGenerating(false)
    }
  }

  const getDeliveryModeColor = (mode: string) => {
    switch (mode) {
      case "Physical":
        return "bg-blue-100 text-blue-800"
      case "Online":
        return "bg-green-100 text-green-800"
      default:
        return "bg-gray-100 text-gray-800"
    }
  }

  if (isLoading && apiData.semesters.length === 0) {
    return (
      <AuthenticatedLayout>
        <Head title="Auto-Generate Timetable" />
        <div className="container mx-auto py-8">
          <div className="flex items-center justify-center h-64">
            <Loader2 className="h-8 w-8 animate-spin" />
            <span className="ml-2">Loading...</span>
          </div>
        </div>
      </AuthenticatedLayout>
    )
  }

  return (
    <AuthenticatedLayout>
      <Head title="Auto-Generate Timetable" />
      <div className="container mx-auto py-8">
        <div className="flex justify-between items-center mb-6">
          <div>
            <h1 className="text-3xl font-bold">Auto-Generate Class Timetable</h1>
            <p className="text-gray-600 mt-2">
              Automatically generate timetables with smart credit hour distribution and lecturer scheduling
            </p>
          </div>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Generation Form */}
          <Card className="lg:col-span-1">
            <CardHeader>
              <CardTitle>Generation Parameters</CardTitle>
              <CardDescription>Select the criteria for timetable generation</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              {error && (
                <Alert variant="destructive">
                  <AlertCircle className="h-4 w-4" />
                  <AlertTitle>Error</AlertTitle>
                  <AlertDescription>{error}</AlertDescription>
                </Alert>
              )}

              {success && (
                <Alert className="bg-green-50 border-green-200 text-green-800">
                  <CheckCircle className="h-4 w-4" />
                  <AlertTitle>Success</AlertTitle>
                  <AlertDescription>{success}</AlertDescription>
                </Alert>
              )}

              <div className="space-y-2">
                <Label htmlFor="semester">Semester *</Label>
                <Select value={formData.semester_id} onValueChange={(value) => handleInputChange("semester_id", value)}>
                  <SelectTrigger>
                    <SelectValue placeholder="Select semester" />
                  </SelectTrigger>
                  <SelectContent>
                    {apiData.semesters.map((semester) => (
                      <SelectItem key={semester.id} value={semester.id.toString()}>
                        {semester.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div className="space-y-2">
                <Label htmlFor="program">Program *</Label>
                <Select
                  value={formData.program_id}
                  onValueChange={(value) => handleInputChange("program_id", value)}
                  disabled={!formData.semester_id || isLoading}
                >
                  <SelectTrigger>
                    <SelectValue placeholder={formData.semester_id ? "Select program" : "Select semester first"} />
                  </SelectTrigger>
                  <SelectContent>
                    {apiData.programs.map((program) => (
                      <SelectItem key={program.id} value={program.id.toString()}>
                        {program.code} - {program.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                {formData.semester_id && apiData.programs.length === 0 && !isLoading && (
                  <p className="text-sm text-amber-600">No programs found for this semester</p>
                )}
              </div>

              <div className="space-y-2">
                <Label htmlFor="class">Class *</Label>
                <Select
                  value={formData.class_id}
                  onValueChange={(value) => handleInputChange("class_id", value)}
                  disabled={!formData.program_id || isLoading}
                >
                  <SelectTrigger>
                    <SelectValue placeholder={formData.program_id ? "Select class" : "Select program first"} />
                  </SelectTrigger>
                  <SelectContent>
                    {apiData.classes.map((cls) => (
                      <SelectItem key={cls.id} value={cls.id.toString()}>
                        {cls.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                {formData.program_id && apiData.classes.length === 0 && !isLoading && (
                  <p className="text-sm text-amber-600">No classes found for this program and semester</p>
                )}
              </div>

              <div className="space-y-2">
                <Label htmlFor="group">Group (Optional)</Label>
                <Select
                  value={formData.group_id}
                  onValueChange={(value) => handleInputChange("group_id", value)}
                  disabled={!formData.class_id || isLoading}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="All groups" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">All groups</SelectItem>
                    {apiData.groups.map((group) => (
                      <SelectItem key={group.id} value={group.id.toString()}>
                        {group.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div className="pt-4">
                <Button
                  onClick={handleGenerate}
                  disabled={isGenerating || !formData.semester_id || !formData.program_id || !formData.class_id}
                  className="w-full"
                >
                  {isGenerating ? (
                    <>
                      <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                      Generating...
                    </>
                  ) : (
                    "Generate Timetable"
                  )}
                </Button>
              </div>

              {/* Credit Hour Distribution Info */}
              <div className="mt-6 p-4 bg-blue-50 rounded-lg">
                <h4 className="font-medium text-blue-900 mb-2">Credit Hour Distribution</h4>
                <div className="text-sm text-blue-800 space-y-1">
                  <div>• 2 Credits: All physical sessions</div>
                  <div>• 3 Credits: 2h physical + 1h online</div>
                  <div>• 4 Credits: 2h physical + 2h physical</div>
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Results */}
          <div className="lg:col-span-2">
            {generatedTimetable.length > 0 ? (
              <Card>
                <CardHeader className="flex flex-row items-center justify-between">
                  <div>
                    <CardTitle>Generated Timetable</CardTitle>
                    {summary && (
                      <CardDescription>
                        {summary.total_sessions} sessions generated • {summary.physical_sessions} physical •{" "}
                        {summary.online_sessions} online
                      </CardDescription>
                    )}
                  </div>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => window.open("/auto-generate-timetable/download", "_blank")}
                  >
                    <Download className="h-4 w-4 mr-2" />
                    Download PDF
                  </Button>
                </CardHeader>
                <CardContent>
                  <Tabs defaultValue="weekly" className="w-full">
                    <TabsList className="grid w-full grid-cols-2">
                      <TabsTrigger value="weekly">Weekly View</TabsTrigger>
                      <TabsTrigger value="lecturer">By Lecturer</TabsTrigger>
                    </TabsList>

                    <TabsContent value="weekly" className="mt-6">
                      <div className="space-y-6">
                        {["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"].map((day) => {
                          const dayEntries = generatedTimetable.filter((entry) => entry.day === day)
                          if (dayEntries.length === 0) return null

                          return (
                            <div key={day} className="border rounded-lg">
                              <div className="bg-gray-100 px-4 py-2 font-medium border-b">{day}</div>
                              <div className="divide-y">
                                {dayEntries
                                  .sort((a, b) => a.start_time.localeCompare(b.start_time))
                                  .map((entry) => (
                                    <div key={entry.id} className="p-4">
                                      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                        <div className="flex-1">
                                          <div className="flex items-center gap-2 mb-2">
                                            <h4 className="font-semibold text-lg">
                                              {entry.unit_code}: {entry.unit_name}
                                            </h4>
                                            <Badge className={getDeliveryModeColor(entry.delivery_mode)}>
                                              {entry.delivery_mode}
                                            </Badge>
                                            <Badge variant="outline">{entry.credit_hours} Credits</Badge>
                                          </div>
                                          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 text-sm text-gray-600">
                                            <div className="flex items-center gap-1">
                                              <Clock className="h-4 w-4" />
                                              {entry.start_time} - {entry.end_time}
                                            </div>
                                            <div className="flex items-center gap-1">
                                              <MapPin className="h-4 w-4" />
                                              {entry.venue} ({entry.location})
                                            </div>
                                            <div className="flex items-center gap-1">
                                              <Users className="h-4 w-4" />
                                              {entry.student_count} students
                                            </div>
                                            <div className="flex items-center gap-1">
                                              <GraduationCap className="h-4 w-4" />
                                              {entry.lecturer}
                                            </div>
                                          </div>
                                        </div>
                                      </div>
                                    </div>
                                  ))}
                              </div>
                            </div>
                          )
                        })}
                      </div>
                    </TabsContent>

                    <TabsContent value="lecturer" className="mt-6">
                      <div className="space-y-6">
                        {Array.from(new Set(generatedTimetable.map((entry) => entry.lecturer))).map((lecturer) => {
                          const lecturerEntries = generatedTimetable.filter((entry) => entry.lecturer === lecturer)

                          return (
                            <div key={lecturer} className="border rounded-lg">
                              <div className="bg-gray-100 px-4 py-2 font-medium border-b flex items-center gap-2">
                                <GraduationCap className="h-5 w-5" />
                                {lecturer}
                              </div>
                              <div className="divide-y">
                                {lecturerEntries
                                  .sort((a, b) => {
                                    const days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"]
                                    const dayOrder = days.indexOf(a.day) - days.indexOf(b.day)
                                    return dayOrder !== 0 ? dayOrder : a.start_time.localeCompare(b.start_time)
                                  })
                                  .map((entry) => (
                                    <div key={entry.id} className="p-3">
                                      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                        <div>
                                          <div className="font-medium">
                                            {entry.unit_code}: {entry.unit_name}
                                          </div>
                                          <div className="text-sm text-gray-600">
                                            {entry.day} • {entry.start_time} - {entry.end_time}
                                          </div>
                                        </div>
                                        <div className="flex gap-2">
                                          <Badge className={getDeliveryModeColor(entry.delivery_mode)}>
                                            {entry.delivery_mode}
                                          </Badge>
                                          <Badge variant="outline">{entry.venue}</Badge>
                                        </div>
                                      </div>
                                    </div>
                                  ))}
                              </div>
                            </div>
                          )
                        })}
                      </div>
                    </TabsContent>
                  </Tabs>
                </CardContent>
              </Card>
            ) : (
              <Card>
                <CardHeader>
                  <CardTitle>No Timetable Generated</CardTitle>
                  <CardDescription>
                    Select your parameters and click "Generate Timetable" to create an optimized schedule.
                  </CardDescription>
                </CardHeader>
                <CardContent className="text-center py-8">
                  <div className="text-gray-500 mb-4">
                    <Clock className="h-12 w-12 mx-auto mb-4 opacity-50" />
                    <p>
                      The system will automatically assign venues and time slots to units with their lecturers,
                      considering credit hours and lecturer availability.
                    </p>
                  </div>
                </CardContent>
              </Card>
            )}
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  )
}
