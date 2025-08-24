"use client"

import { useState } from "react"
import { Button } from "@/components/ui/button"
import { Alert, AlertDescription } from "@/components/ui/alert"
import { Badge } from "@/components/ui/badge"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Progress } from "@/components/ui/progress"
import { AlertCircle, CheckCircle, Clock, RefreshCw, Settings, Brain, Target, Shuffle, BarChart3 } from "lucide-react"
import { toast } from "react-hot-toast"
import axios from "axios"

interface AdvancedCSPSolverProps {
  classTimetables: any
  constraints: any
  detectedConflicts: any[]
  onScheduleOptimized: () => void
}

export default function AdvancedCSPSolverComponent({
  classTimetables,
  constraints,
  detectedConflicts,
  onScheduleOptimized,
}: AdvancedCSPSolverProps) {
  const [isOptimizing, setIsOptimizing] = useState(false)
  const [isGenerating, setIsGenerating] = useState(false)
  const [algorithm, setAlgorithm] = useState("simulated_annealing")
  const [optimizationResult, setOptimizationResult] = useState<any>(null)
  const [generationResult, setGenerationResult] = useState<any>(null)
  const [progress, setProgress] = useState(0)

  const optimizeSchedule = async (selectedAlgorithm: string = algorithm) => {
    setIsOptimizing(true)
    setOptimizationResult(null)
    setProgress(0)

    // Simulate progress
    const progressInterval = setInterval(() => {
      setProgress((prev) => Math.min(prev + Math.random() * 15, 90))
    }, 500)

    try {
      const response = await axios.post("/api/optimize-schedule", {
        algorithm: selectedAlgorithm,
        semester_id: null,
        class_id: null,
        group_id: null,
      })

      clearInterval(progressInterval)
      setProgress(100)

      if (response.data.success) {
        setOptimizationResult(response.data.optimization_result)
        toast.success(`🧠 Schedule optimized using ${selectedAlgorithm}!`)
        onScheduleOptimized()
      } else {
        toast.error("Failed to optimize schedule")
      }
    } catch (error: any) {
      clearInterval(progressInterval)
      console.error("Error optimizing schedule:", error)
      toast.error(error.response?.data?.message || "Failed to optimize schedule")
    } finally {
      setIsOptimizing(false)
      setProgress(0)
    }
  }

  const generateOptimalSchedule = async () => {
    if (
      !confirm("This will delete all existing timetables and generate a completely new optimal schedule. Continue?")
    ) {
      return
    }

    setIsGenerating(true)
    setGenerationResult(null)
    setProgress(0)

    const progressInterval = setInterval(() => {
      setProgress((prev) => Math.min(prev + Math.random() * 10, 85))
    }, 800)

    try {
      const response = await axios.post("/api/generate-optimal-schedule", {
        semester_id: null,
        class_id: null,
        group_id: null,
      })

      clearInterval(progressInterval)
      setProgress(100)

      if (response.data.success) {
        setGenerationResult(response.data.generation_result)
        toast.success("🎯 Optimal schedule generated from scratch!")
        onScheduleOptimized()
      } else {
        toast.error("Failed to generate optimal schedule")
      }
    } catch (error: any) {
      clearInterval(progressInterval)
      console.error("Error generating schedule:", error)
      toast.error(error.response?.data?.message || "Failed to generate schedule")
    } finally {
      setIsGenerating(false)
      setProgress(0)
    }
  }

  const getAlgorithmDescription = (alg: string) => {
    switch (alg) {
      case "simulated_annealing":
        return "Uses temperature-based optimization to escape local optima and find global solutions"
      case "genetic":
        return "Evolves solutions through selection, crossover, and mutation operations"
      case "backtracking":
        return "Systematically explores all possibilities to find the first valid solution"
      default:
        return "Unknown algorithm"
    }
  }

  const getAlgorithmIcon = (alg: string) => {
    switch (alg) {
      case "simulated_annealing":
        return Brain
      case "genetic":
        return Shuffle
      case "backtracking":
        return Target
      default:
        return Settings
    }
  }

  return (
    <div className="space-y-6">
      {/* CSP Solver Controls */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center">
            <Brain className="w-5 h-5 mr-2 text-purple-500" />
            Advanced CSP Schedule Optimizer
          </CardTitle>
          <CardDescription>
            Rearrange your entire schedule using Constraint Satisfaction Problem solving algorithms
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-6">
          {/* Algorithm Selection */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-3">Optimization Algorithm</label>
            <div className="space-y-4">
              {[
                {
                  value: "simulated_annealing",
                  label: "Simulated Annealing",
                  icon: Brain,
                  complexity: "High",
                  speed: "Medium",
                  quality: "Excellent",
                },
                {
                  value: "genetic",
                  label: "Genetic Algorithm",
                  icon: Shuffle,
                  complexity: "Very High",
                  speed: "Slow",
                  quality: "Excellent",
                },
                {
                  value: "backtracking",
                  label: "Backtracking Search",
                  icon: Target,
                  complexity: "Medium",
                  speed: "Fast",
                  quality: "Good",
                },
              ].map((alg) => (
                <div key={alg.value} className="border rounded-lg p-4 hover:bg-gray-50 transition-colors">
                  <div className="flex items-start space-x-3">
                    <input
                      type="radio"
                      id={alg.value}
                      name="algorithm"
                      value={alg.value}
                      checked={algorithm === alg.value}
                      onChange={(e) => setAlgorithm(e.target.value)}
                      className="mt-1"
                    />
                    <div className="flex-1">
                      <label htmlFor={alg.value} className="flex items-center cursor-pointer mb-2">
                        <alg.icon className="w-5 h-5 mr-2 text-purple-500" />
                        <span className="font-medium text-gray-900">{alg.label}</span>
                      </label>
                      <p className="text-sm text-gray-600 mb-3">{getAlgorithmDescription(alg.value)}</p>

                      <div className="flex space-x-4 text-xs">
                        <div className="flex items-center">
                          <span className="text-gray-500 mr-1">Complexity:</span>
                          <Badge variant="outline" className="text-xs">
                            {alg.complexity}
                          </Badge>
                        </div>
                        <div className="flex items-center">
                          <span className="text-gray-500 mr-1">Speed:</span>
                          <Badge variant="outline" className="text-xs">
                            {alg.speed}
                          </Badge>
                        </div>
                        <div className="flex items-center">
                          <span className="text-gray-500 mr-1">Quality:</span>
                          <Badge variant="outline" className="text-xs">
                            {alg.quality}
                          </Badge>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>

          {/* Progress Bar */}
          {(isOptimizing || isGenerating) && (
            <div className="space-y-2">
              <div className="flex justify-between text-sm">
                <span>{isOptimizing ? "Optimizing Schedule..." : "Generating Schedule..."}</span>
                <span>{Math.round(progress)}%</span>
              </div>
              <Progress value={progress} className="w-full" />
              <p className="text-xs text-gray-500">
                {isOptimizing
                  ? "Analyzing conflicts and rearranging sessions for optimal fit..."
                  : "Creating completely new schedule from scratch..."}
              </p>
            </div>
          )}

          {/* Action Buttons */}
          <div className="flex flex-col sm:flex-row gap-3 pt-4 border-t">
            <Button
              onClick={() => optimizeSchedule()}
              disabled={isOptimizing || isGenerating}
              className="bg-purple-500 hover:bg-purple-600 text-white flex-1"
            >
              {isOptimizing ? (
                <>
                  <Clock className="w-4 h-4 mr-2 animate-spin" />
                  Optimizing...
                </>
              ) : (
                <>
                  <Brain className="w-4 h-4 mr-2" />
                  Optimize Current Schedule
                </>
              )}
            </Button>

            <Button
              onClick={generateOptimalSchedule}
              disabled={isOptimizing || isGenerating}
              variant="outline"
              className="border-purple-300 text-purple-700 hover:bg-purple-50 flex-1"
            >
              {isGenerating ? (
                <>
                  <Clock className="w-4 h-4 mr-2 animate-spin" />
                  Generating...
                </>
              ) : (
                <>
                  <RefreshCw className="w-4 h-4 mr-2" />
                  Generate From Scratch
                </>
              )}
            </Button>
          </div>

          <div className="text-xs text-gray-500 bg-gray-50 p-3 rounded">
            <strong>Tip:</strong> "Optimize Current" improves existing schedule, while "Generate From Scratch" creates a
            completely new optimal schedule (deletes current data).
          </div>
        </CardContent>
      </Card>

      {/* Optimization Results */}
      {optimizationResult && (
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center">
              <BarChart3 className="w-5 h-5 mr-2 text-green-500" />
              Optimization Results
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-6">
              {/* Performance Metrics */}
              <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div className="text-center p-4 bg-green-50 rounded-lg border border-green-200">
                  <div className="text-2xl font-bold text-green-600">
                    {optimizationResult.improvement_percentage.toFixed(1)}%
                  </div>
                  <div className="text-sm text-green-700">Improvement</div>
                </div>

                <div className="text-center p-4 bg-blue-50 rounded-lg border border-blue-200">
                  <div className="text-2xl font-bold text-blue-600">{optimizationResult.iterations}</div>
                  <div className="text-sm text-blue-700">Iterations</div>
                </div>

                <div className="text-center p-4 bg-red-50 rounded-lg border border-red-200">
                  <div className="text-2xl font-bold text-red-600">
                    {optimizationResult.initial_conflicts} → {optimizationResult.final_conflicts}
                  </div>
                  <div className="text-sm text-red-700">Conflicts</div>
                </div>

                <div className="text-center p-4 bg-gray-50 rounded-lg border border-gray-200">
                  <div className="text-2xl font-bold text-gray-600">{optimizationResult.execution_time}s</div>
                  <div className="text-sm text-gray-700">Time Taken</div>
                </div>
              </div>

              {/* Algorithm Details */}
              <div className="bg-purple-50 border border-purple-200 rounded-lg p-4">
                <div className="flex items-center mb-2">
                  {(() => {
                    const AlgorithmIcon = getAlgorithmIcon(optimizationResult.algorithm_used)
                    return <AlgorithmIcon className="w-5 h-5 mr-2 text-purple-600" />
                  })()}
                  <h4 className="font-medium text-purple-800">
                    {optimizationResult.algorithm_used.replace("_", " ").toUpperCase()} Algorithm
                  </h4>
                </div>
                <p className="text-sm text-purple-700">{getAlgorithmDescription(optimizationResult.algorithm_used)}</p>
              </div>

              {/* Changes Applied */}
              {optimizationResult.changes_applied && optimizationResult.changes_applied.count > 0 && (
                <div>
                  <h4 className="font-medium text-gray-900 mb-3">
                    Changes Applied ({optimizationResult.changes_applied.count} sessions modified):
                  </h4>
                  <div className="space-y-2 max-h-60 overflow-y-auto">
                    {optimizationResult.changes_applied.details.slice(0, 10).map((change: any, index: number) => (
                      <div key={index} className="flex items-start space-x-2 p-2 bg-gray-50 rounded">
                        <CheckCircle className="w-4 h-4 text-green-500 mt-0.5 flex-shrink-0" />
                        <div className="text-sm">
                          <span className="font-medium">{change.session}</span>
                          {change.group && <span className="text-gray-500"> ({change.group})</span>}
                          <div className="text-xs text-gray-600 mt-1">{change.changes.join(", ")}</div>
                        </div>
                      </div>
                    ))}
                    {optimizationResult.changes_applied.details.length > 10 && (
                      <div className="text-center text-sm text-gray-500 pt-2">
                        ... and {optimizationResult.changes_applied.details.length - 10} more changes
                      </div>
                    )}
                  </div>
                </div>
              )}

              {/* Success Message */}
              <Alert className="border-green-200 bg-green-50">
                <CheckCircle className="h-4 w-4 text-green-600" />
                <AlertDescription className="text-green-700">
                  <strong>Optimization Complete!</strong> Your schedule has been intelligently rearranged to minimize
                  conflicts while respecting all constraints.
                </AlertDescription>
              </Alert>
            </div>
          </CardContent>
        </Card>
      )}

      {/* Generation Results */}
      {generationResult && (
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center">
              <Target className="w-5 h-5 mr-2 text-blue-500" />
              Schedule Generation Results
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              {/* Generation Metrics */}
              <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div className="text-center p-4 bg-red-50 rounded-lg border border-red-200">
                  <div className="text-2xl font-bold text-red-600">{generationResult.deleted_sessions}</div>
                  <div className="text-sm text-red-700">Deleted</div>
                </div>

                <div className="text-center p-4 bg-green-50 rounded-lg border border-green-200">
                  <div className="text-2xl font-bold text-green-600">{generationResult.created_sessions}</div>
                  <div className="text-sm text-green-700">Created</div>
                </div>

                <div className="text-center p-4 bg-blue-50 rounded-lg border border-blue-200">
                  <div className="text-2xl font-bold text-blue-600">{generationResult.total_conflicts}</div>
                  <div className="text-sm text-blue-700">Final Conflicts</div>
                </div>

                <div className="text-center p-4 bg-purple-50 rounded-lg border border-purple-200">
                  <div className="text-2xl font-bold text-purple-600">
                    {(generationResult.constraint_satisfaction * 100).toFixed(1)}%
                  </div>
                  <div className="text-sm text-purple-700">Satisfaction</div>
                </div>
              </div>

              {/* Success Message */}
              <Alert className="border-blue-200 bg-blue-50">
                <Target className="h-4 w-4 text-blue-600" />
                <AlertDescription className="text-blue-700">
                  <strong>New Schedule Generated!</strong> A completely optimal schedule has been created from scratch
                  with minimal conflicts and maximum constraint satisfaction.
                </AlertDescription>
              </Alert>
            </div>
          </CardContent>
        </Card>
      )}

      {/* Current Conflicts Info */}
      {detectedConflicts.length > 0 && !optimizationResult && !generationResult && (
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center">
              <AlertCircle className="w-5 h-5 mr-2 text-orange-500" />
              Conflicts to Resolve ({detectedConflicts.length})
            </CardTitle>
            <CardDescription>These conflicts will be automatically resolved by the CSP optimizer</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="space-y-2">
              {detectedConflicts.slice(0, 3).map((conflict, index) => (
                <div
                  key={index}
                  className="flex items-center space-x-2 p-2 bg-orange-50 rounded border border-orange-200"
                >
                  <AlertCircle className="w-4 h-4 text-orange-500 flex-shrink-0" />
                  <span className="text-sm text-orange-700">{conflict.description}</span>
                </div>
              ))}
              {detectedConflicts.length > 3 && (
                <div className="text-center text-sm text-gray-500 pt-2">
                  ... and {detectedConflicts.length - 3} more conflicts
                </div>
              )}
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  )
}
