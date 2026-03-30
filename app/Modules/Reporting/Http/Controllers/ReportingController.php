<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reporting\Services\ReportingService;
use Illuminate\Http\Request;

class ReportingController extends Controller
{
    public function __construct(
        private ReportingService $reportingService
    ) {}

    public function executive(Request $request)
    {
        $user = auth()->user();
        $isSalesAgent = $user->isRole('Sales') || $user->isRole('CallAgent');
        
        $filters = $request->only(['from', 'to', 'agent_id', 'city', 'postcode', 'source']);
        
        // For sales agents, force filter by their ID
        if ($isSalesAgent) {
            $filters['agent_id'] = $user->id;
        }
        
        $data = $this->reportingService->getExecutiveDashboard($filters);

        // Get agent performance (only for admin/manager)
        if (!$isSalesAgent) {
            $data['agents'] = $this->reportingService->getAgentPerformance($filters);
        }

        return response()->json($data);
    }

    public function funnel(Request $request)
    {
        $user = auth()->user();
        $isSalesAgent = $user->isRole('Sales') || $user->isRole('CallAgent');
        
        $filters = $request->only(['from', 'to', 'agent_id', 'source']);
        
        // For sales agents, force filter by their ID
        if ($isSalesAgent) {
            $filters['agent_id'] = $user->id;
        }
        
        $data = $this->reportingService->getFunnelReport($filters);

        return response()->json($data);
    }

    public function geo(Request $request)
    {
        $filters = $request->only(['city', 'postcode']);
        $data = $this->reportingService->getGeoAnalytics($filters);

        return response()->json($data);
    }

    public function communications(Request $request)
    {
        $user = auth()->user();
        $isSalesAgent = $user->isRole('Sales') || $user->isRole('CallAgent');
        
        $filters = $request->only(['from', 'to', 'agent_id']);
        
        // For sales agents, force filter by their ID
        if ($isSalesAgent) {
            $filters['agent_id'] = $user->id;
        }
        
        $data = $this->reportingService->getCommunicationAnalytics($filters);

        return response()->json($data);
    }

    public function agents(Request $request)
    {
        $filters = $request->only(['from', 'to', 'month', 'agent_id']);
        $data = $this->reportingService->getAgentPerformance($filters);

        return response()->json($data);
    }

    public function allEmployeesPipeline(Request $request)
    {
        $user = auth()->user();
        $isSalesAgent = $user->isRole('Sales') || $user->isRole('CallAgent');
        
        // Only admin/manager can see all employees pipeline
        if ($isSalesAgent) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $filters = $request->only(['from', 'to', 'agent_id']);
        $data = $this->reportingService->getAllEmployeesPipeline($filters);

        return response()->json($data);
    }

    public function todaysFollowUps(Request $request)
    {
        $user = auth()->user();
        $isSalesAgent = $user->isRole('Sales') || $user->isRole('CallAgent');
        
        $filters = $request->only(['agent_id']);
        
        // For sales agents, force filter by their ID
        if ($isSalesAgent) {
            $filters['agent_id'] = $user->id;
        }
        
        $data = $this->reportingService->getTodaysFollowUps($filters);

        return response()->json($data);
    }

    public function salesPerformance(Request $request)
    {
        $user = auth()->user();
        $isSalesAgent = $user->isRole('Sales') || $user->isRole('CallAgent');
        
        $filters = $request->only(['period', 'agent_id']);
        
        // For sales agents, force filter by their ID
        if ($isSalesAgent) {
            $filters['agent_id'] = $user->id;
        }
        
        $data = $this->reportingService->getSalesPerformance($filters);

        return response()->json($data);
    }

    public function revenueByEmployee(Request $request)
    {
        $user = auth()->user();
        $isSalesAgent = $user->isRole('Sales') || $user->isRole('CallAgent');
        
        // Only admin/manager can see revenue by all employees
        if ($isSalesAgent) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $filters = $request->only(['from', 'to', 'agent_id']);
        $data = $this->reportingService->getRevenueByEmployee($filters);

        return response()->json($data);
    }

    public function teamLocationStatus(Request $request)
    {
        $filters = $request->only(['from', 'to']);
        $data = $this->reportingService->getTeamLocationStatus($filters);

        return response()->json($data);
    }

    /**
     * Products sold by a selected employee this month. Admin only.
     */
    public function productsSoldByEmployee(Request $request)
    {
        $user = auth()->user();
        $isAdmin = $user->isRole('Admin') || $user->isRole('Manager') || $user->isRole('System Admin');
        if (!$isAdmin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $filters = $request->only(['from', 'to', 'agent_id']);
        $data = $this->reportingService->getProductsSoldByEmployee($filters);

        return response()->json($data);
    }

    /**
     * Target vs Achievement summary for all employees. Admin only.
     */
    public function targetVsAchievement(Request $request)
    {
        $user = auth()->user();
        $isAdmin = $user->isRole('Admin') || $user->isRole('Manager') || $user->isRole('System Admin');
        if (!$isAdmin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $filters = $request->only(['month']);
        $data = $this->reportingService->getTargetVsAchievementSummary($filters);

        return response()->json($data);
    }

    /**
     * Employee self report: target vs achievement + ranking. Employees see only their own.
     */
    public function employeeSelfReport(Request $request)
    {
        $user = auth()->user();
        $userId = $request->get('agent_id');
        $isAdmin = $user->isRole('Admin') || $user->isRole('Manager') || $user->isRole('System Admin');

        if (!$isAdmin) {
            $userId = $user->id;
        } elseif (!$userId) {
            $userId = $user->id;
        }

        $filters = $request->only(['month']);
        $data = $this->reportingService->getEmployeeSelfReport((int) $userId, $filters);

        return response()->json($data);
    }
}


