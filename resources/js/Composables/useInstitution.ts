import { usePage } from '@inertiajs/vue3';

export type Institution = {
    name: string;
    short_name: string;
    phone: string;
    phone_uri: string;
    whatsapp_uri: string;
    address: string;
    hours: string;
    availability: string;
    emergencies: string;
    timezone: string;
    provider: { name: string; credential_type: string; credential_number: string };
};

export const useInstitution = (): Institution => usePage().props.institution as Institution;
