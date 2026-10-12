<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employer;

use App\Actions\Employers\IssueWhatsAppLinkCode;
use App\Http\Controllers\Controller;
use App\Models\EmployerWhatsappLink;
use App\Models\EmployerWhatsappMessage;
use App\Models\EmployerWorkspace;
use App\Support\Audit\AuditLogger;
use App\Support\Employers\ResolvesMembership;
use App\Support\Employers\WhatsAppBusinessNumber;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Post jobs on WhatsApp": the member's QR code (opens WhatsApp with
 * "JOIN <code>" ready to send to the business number), the phones already
 * linked to this workspace, and the latest messages in those chats.
 * Only owners and recruiters — the people who can publish a JD — get a code.
 */
final class WhatsAppLinkController extends Controller
{
    use ResolvesMembership;

    public function show(Request $request, EmployerWorkspace $workspace, IssueWhatsAppLinkCode $issue, WhatsAppBusinessNumber $number): JsonResponse
    {
        $member = $this->membershipOrFail($workspace, $request->user());

        return response()->json(['data' => $this->payload($workspace, $member->role->managesPipeline()
            ? $issue->handle($workspace, $request->user())->code
            : null, $number)]);
    }

    public function regenerate(Request $request, EmployerWorkspace $workspace, IssueWhatsAppLinkCode $issue, WhatsAppBusinessNumber $number, AuditLogger $audit): JsonResponse
    {
        $member = $this->membershipOrFail($workspace, $request->user());
        abort_unless($member->role->managesPipeline(), 403);

        $code = $issue->handle($workspace, $request->user(), fresh: true);
        $audit->log('employer.whatsapp_code_regenerated', $workspace, [], $request->user());

        return response()->json(['data' => $this->payload($workspace, $code->code, $number)]);
    }

    public function unlink(Request $request, EmployerWorkspace $workspace, EmployerWhatsappLink $link, AuditLogger $audit): JsonResponse
    {
        $member = $this->membershipOrFail($workspace, $request->user());
        abort_unless($member->role->managesPipeline(), 403);
        abort_unless($link->employer_workspace_id === $workspace->id, 404);

        $audit->log('employer.whatsapp_unlinked', $link, ['phone_last4' => substr($link->phone, -4)], $request->user());
        $link->delete();

        return response()->json(['data' => ['ok' => true]]);
    }

    /** @return array<string, mixed> */
    private function payload(EmployerWorkspace $workspace, ?string $code, WhatsAppBusinessNumber $number): array
    {
        $business = $number->get();
        $url = $code !== null && $business !== null
            ? 'https://wa.me/'.$business.'?text='.rawurlencode("JOIN {$code}")
            : null;

        $links = EmployerWhatsappLink::query()
            ->where('employer_workspace_id', $workspace->id)
            ->with('user:id,name')
            ->latest('id')
            ->get();

        $messages = EmployerWhatsappMessage::query()
            ->whereIn('employer_whatsapp_link_id', $links->pluck('id'))
            ->latest('id')
            ->limit(30)
            ->get()
            ->reverse()
            ->values();

        return [
            'code' => $code,
            'business_number' => $business,
            'chat_url' => $url,
            'qr_svg' => $url !== null ? (new SvgWriter)->write(new QrCode($url))->getString() : null,
            'links' => $links->map(fn (EmployerWhatsappLink $l) => [
                'id' => $l->id,
                'phone' => '+'.$l->phone,
                'profile_name' => $l->profile_name,
                'acts_as' => $l->user?->name,
                'state' => $l->state,
                'last_message_at' => $l->last_inbound_at?->toIso8601String(),
            ])->values(),
            'messages' => $messages->map(fn (EmployerWhatsappMessage $m) => [
                'id' => $m->id,
                'phone' => '+'.$m->phone,
                'direction' => $m->direction,
                'body' => $m->body,
                'status' => $m->status,
                'at' => $m->created_at?->toIso8601String(),
            ])->values(),
        ];
    }
}
