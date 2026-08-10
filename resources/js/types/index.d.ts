export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
}

export interface Specialty { id: string; name: string; slug: string; short_description?: string | null; description?: string | null; common_reasons?: string[] | null; services?: string[] | null; image_path?: string | null; icon?: string | null; seo_title?: string | null; seo_description?: string | null; sort_order: number; is_active: boolean; is_public: boolean; doctors?: Doctor[]; pivot?: { is_primary: boolean } }
export interface Doctor { id: string; user_id?: string | null; first_name: string; last_name: string; professional_name?: string | null; credential_type?: string | null; credential_number?: string | null; email?: string | null; phone?: string | null; biography?: string | null; schedules?: unknown[] | null; photo_path?: string | null; specialties?: Specialty[]; is_active: boolean; is_public: boolean }
export interface Patient { id: string; first_name: string; last_name: string; document_type?: string | null; document_number?: string | null; birth_date?: string | null; age?: number | null; sex?: string | null; email?: string | null; phone?: string | null; address?: string | null }
export interface Clinic { id: string; code: string; slug?: string; name: string; department: string; latitude: number | null; longitude: number | null; address?: string | null; status: 'PLANNED'|'CONFIRMED'|'ACTIVE'|'INACTIVE'; is_public: boolean }
export interface PdfTemplate { id: string; name: string; document_type?: string | null; page_size?: 'A4'|'LETTER'|'LEGAL'|null; qr_page: number; qr_x: number; qr_y: number; qr_width: number; qr_height: number; coordinates?: Record<string, unknown> | null; is_active: boolean; created_at?: string }
export interface ExtractionCandidate { field?: string; value?: unknown; text?: unknown; content?: unknown; source?: string; confidence?: number; [key: string]: unknown }
export interface MedicalDocument { id: string; type: string; status: string; certificate_kind?: string | null; public_code?: string | null; original_filename: string; patient?: Patient | null; doctor?: Doctor | null; clinic?: Clinic | null; confirmed_fields?: Record<string, string | null>; inconsistencies?: unknown[]; processing_metadata?: Record<string, unknown> | null; extractions?: Array<{ candidates?: Record<string, unknown>|ExtractionCandidate[]; warnings?: unknown[]; quality_score?: string | number }>; verification_logs_count?: number; issued_at?: string | null; revoked_at?: string | null; revocation_reason?: string | null; created_at: string }
export interface Setting { id: string; key: string; value: boolean; is_public: boolean }
export interface SitePage { id: string; slug: string; title: string; content?: string | null; meta_title?: string | null; meta_description?: string | null; is_published: boolean }
export interface PaginationLink { url: string | null; label: string; active: boolean }
export interface Paginated<T> { data: T[]; links: PaginationLink[]; current_page: number; last_page: number; total: number; from?: number | null; to?: number | null }

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User | null;
    };
    flash?: { status?: string };
};
